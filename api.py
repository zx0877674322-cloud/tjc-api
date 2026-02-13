from flask import Flask, request, jsonify
from pytesseract import Output
import pytesseract
import cv2
import numpy as np
import re
import time
from difflib import SequenceMatcher # ✅ เพิ่มไลบรารีสำหรับเปรียบเทียบความเหมือน

app = Flask(__name__)
# ⚙️ ตรวจสอบ Path Tesseract
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# ==========================================
# 🧠 Memory & Comparison Logic (สมองส่วนจดจำและเปรียบเทียบ)
# ==========================================

def similar(a, b):
    """ ให้คะแนนความเหมือน 0.0 ถึง 1.0 (เช่น 0.9 = เหมือน 90%) """
    return SequenceMatcher(None, a, b).ratio()

def clean_text_for_search(text):
    if not text: return ""
    # เก็บ ก-ฮ, A-Z, 0-9
    text = re.sub(r'[^a-zA-Z0-9ก-๙]', '', text)
    return text.lower()

def morph_str(text):
    """ 
    📝 ส่วนความจำ (Memory):
    แปลงตัวอักษรที่ AI ชอบอ่านผิด ให้เป็นค่ามาตรฐานเดียวกัน
    """
    if not text: return ""
    text = text.upper()
    
    # กลุ่มที่หน้าตาเหมือนกัน (จำไว้ว่าถ้าเจอแบบนี้คือตัวเดียวกัน)
    # แปลง I, l, !, | -> 1
    text = re.sub(r'[Il!|]', '1', text)
    # แปลง O, Q, D, C, G -> 0
    text = re.sub(r'[OQDCG]', '0', text)
    # แปลง Z -> 2
    text = text.replace('Z', '2')
    # แปลง S, $ -> 5
    text = re.sub(r'[S$]', '5', text)
    # แปลง B, 8 -> 8
    text = text.replace('B', '8')
    
    # ลบอักขระพิเศษออก เหลือแค่ A-Z, 0-9 เพื่อเทียบ
    return re.sub(r'[^A-Z0-9]', '', text)

# ==========================================
# 🔍 Image Filters (ชุดเสถียรเดิม)
# ==========================================
def filter_normal(img): return cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
def filter_threshold(img): return cv2.threshold(cv2.cvtColor(img, cv2.COLOR_BGR2GRAY), 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)[1]
def filter_zoom(img): return cv2.resize(cv2.cvtColor(img, cv2.COLOR_BGR2GRAY), None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
def filter_denoise(img): return cv2.fastNlMeansDenoising(cv2.cvtColor(img, cv2.COLOR_BGR2GRAY), None, 10, 7, 21)
def filter_invert(img): return cv2.bitwise_not(cv2.cvtColor(img, cv2.COLOR_BGR2GRAY))
def filter_dilate(img): return cv2.dilate(filter_threshold(img), np.ones((2,2), np.uint8), iterations=1)

# ==========================================
# 🧠 Extraction Logic
# ==========================================
def extract_data_from_image(processed_img, psm_mode=6):
    config_str = f'--psm {psm_mode}'
    d = pytesseract.image_to_data(processed_img, lang='tha+eng', config=config_str, output_type=Output.DICT)
    n_boxes = len(d['text'])
    
    data = {
        "inv_candidates": [],
        "number_candidates": [], 
        "full_text_clean": "",   
        "lines": []
    }
    
    current_line = []
    last_line_num = -1

    for i in range(n_boxes):
        text = d['text'][i].strip()
        if not text: continue
        
        line_num = d['line_num'][i]
        if line_num != last_line_num:
            if current_line: data['lines'].append(" ".join(current_line))
            current_line = []
            last_line_num = line_num
        current_line.append(text)

        # เก็บตัวเลข
        clean_num = text.replace(',', '')
        if re.match(r'^\d+\.\d{2}$', clean_num):
            try:
                val = float(clean_num)
                data['number_candidates'].append({
                    'val': val,
                    'box': [d['left'][i], d['top'][i], d['width'][i], d['height'][i]]
                })
            except: pass

        # เก็บเลขที่บิล
        if len(text) > 4 and any(c.isdigit() for c in text):
             if not re.search(r'\d{2}/\d{2}', text):
                 data['inv_candidates'].append({
                     'text': text,
                     'box': [d['left'][i], d['top'][i], d['width'][i], d['height'][i]]
                 })
    
    if current_line: data['lines'].append(" ".join(current_line))
    raw_text = " ".join(data['lines'])
    data['full_text_clean'] = clean_text_for_search(raw_text)
    
    return data

# ==========================================
# 🚀 API Process Loop
# ==========================================
@app.route('/process_invoice', methods=['POST'])
def process_invoice():
    start_time = time.time()

    try:
        if 'ajax_file' not in request.files: return jsonify({'status': 'error', 'msg': 'No file'})
        
        file = request.files['ajax_file']
        hint_inv = request.form.get('hint_inv', '').strip().upper()
        hint_total = request.form.get('hint_total', '').strip()
        hint_vat = request.form.get('hint_vat', '').strip()
        hint_vendor = request.form.get('hint_vendor', '').strip()
        
        in_memory_file = file.read()
        nparr = np.frombuffer(in_memory_file, np.uint8)
        original_img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        final_result = { 
            "inv_no": "", "inv_no_box": [], 
            "total": 0.0, "total_box": [], 
            "vat": 0.0, "vat_box": [], 
            "vendor_found": False,
            "text_preview": "",
            "debug_vendor": "", "debug_numbers": [],
            "match_score": 0 # เพิ่มคะแนนความเหมือนให้ดูเล่น
        }
        
        is_blind_mode = (not hint_inv) and (not hint_total or float(hint_total) == 0)

        # Strategies (ชุดเดิมที่เสถียร)
        if is_blind_mode:
            strategies = [{'func': filter_normal, 'psm': 6, 'name': 'Normal'}, {'func': filter_threshold, 'psm': 6, 'name': 'Thresh'}, {'func': filter_zoom, 'psm': 6, 'name': 'Zoom'}]
        else:
            strategies = [
                {'func': filter_normal, 'psm': 6, 'name': 'Normal'},
                {'func': filter_zoom, 'psm': 4, 'name': 'Zoom+LineScan'},
                {'func': filter_threshold, 'psm': 6, 'name': 'Thresh'},
                {'func': filter_zoom, 'psm': 6, 'name': 'Zoom+Block'},
                {'func': filter_invert, 'psm': 6, 'name': 'Invert'},
                {'func': filter_dilate, 'psm': 4, 'name': 'Dilate+LineScan'}
            ]

        targets = {
            'inv': False if hint_inv else True,
            'total': False if (hint_total and float(hint_total)>0) else True,
            'vat': False if (hint_vat and float(hint_vat)>0) else True,
            'vendor': False if hint_vendor else True
        }
        if is_blind_mode: targets = {k:False for k in targets}

        print(f"🎯 Target: INV={hint_inv}, TOTAL={hint_total}")

        # --- LOOP SCAN ---
        for i, strat in enumerate(strategies):
            if not is_blind_mode and all(targets.values()): 
                print("✅ All Targets Found!"); break
            
            print(f"🔄 Pass {i+1}: {strat['name']}")
            processed_img = strat['func'](original_img)
            scale = 1.0
            if 'Zoom' in strat['name']: scale = 1/2.0 

            data = extract_data_from_image(processed_img, psm_mode=strat['psm'])
            
            if len(" ".join(data['lines'])) > len(final_result['text_preview']):
                final_result['text_preview'] = " ".join(data['lines'])
                final_result['debug_vendor'] = " ".join(data['lines'][:5])
            
            current_nums = [n['val'] for n in data['number_candidates']]
            if len(current_nums) > len(final_result['debug_numbers']):
                final_result['debug_numbers'] = current_nums

            # --- MATCHING WITH COMPARISON (ส่วนที่เพิ่มความฉลาด) ---
            
            # 1. Vendor (Fuzzy Search > 60%)
            if not targets['vendor'] and hint_vendor:
                clean_hint = clean_text_for_search(hint_vendor)
                full_text = data['full_text_clean']
                
                # หาแบบ Exact
                if clean_hint in full_text:
                    final_result['vendor_found'] = True
                    targets['vendor'] = True
                    print(f"   -> Found Vendor (Exact)")
                else:
                    # หาแบบ Fuzzy (เผื่อตัวอักษรเพี้ยน)
                    score = similar(clean_hint, full_text[:len(clean_hint)+30])
                    if score > 0.6: 
                        final_result['vendor_found'] = True
                        targets['vendor'] = True
                        print(f"   -> Found Vendor (Fuzzy {int(score*100)}%)")

            # 2. Total & VAT
            for num in data['number_candidates']:
                val = num['val']
                real_box = [int(v * scale) for v in num['box']]
                if not targets['total'] and hint_total and abs(val - float(hint_total)) < 1.0:
                    final_result['total'] = val
                    final_result['total_box'] = real_box
                    targets['total'] = True
                    print(f"   -> Found Total: {val}")
                if not targets['vat'] and hint_vat and abs(val - float(hint_vat)) < 1.0:
                    final_result['vat'] = val
                    final_result['vat_box'] = real_box
                    targets['vat'] = True
                    print(f"   -> Found VAT: {val}")

            # 3. Inv No (ใช้ Morph Memory + Fuzzy Compare)
            if not targets['inv'] and hint_inv:
                clean_hint_inv = morph_str(hint_inv)
                for item in data['inv_candidates']:
                    clean_found = morph_str(item['text'])
                    
                    # เทียบเปอร์เซ็นต์ความเหมือน
                    sim_score = similar(clean_hint_inv, clean_found)
                    
                    if sim_score > 0.8: # ยอมรับที่ความเหมือน 80% ขึ้นไป (AI จำได้ว่าคล้ายกัน)
                        final_result['inv_no'] = item['text'] # ส่งค่าเดิมที่อ่านได้ไป
                        final_result['inv_no_box'] = [int(v * scale) for v in item['box']]
                        final_result['match_score'] = int(sim_score * 100) # ส่งคะแนนกลับไปให้ดู
                        targets['inv'] = True
                        print(f"   -> Found INV ({int(sim_score*100)}% Match): {item['text']} vs {hint_inv}")
                        break
            
            # Blind Mode
            if is_blind_mode:
                if data['inv_candidates']: final_result['inv_no'] = data['inv_candidates'][0]['text']
                if data['number_candidates']: final_result['total'] = max(n['val'] for n in data['number_candidates'])
                if final_result['inv_no'] and final_result['total'] > 0: break

        # Cleanup
        if not final_result['inv_no']: final_result['inv_no'] = ""
        final_result['debug_numbers'] = list(set(final_result['debug_numbers']))
        execution_time = f"{round(time.time() - start_time, 2)}s"

        return jsonify({
            "status": "success",
            "execution_time": execution_time,
            "extracted": final_result
        })

    except Exception as e:
        print(e)
        return jsonify({'status': 'error', 'msg': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)