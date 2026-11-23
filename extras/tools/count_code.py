import os
import re

extensions = {'.php', '.js', '.css', '.html', '.sql', '.py', '.json', '.md', '.htaccess'}
exclude_dirs = {'.git', '.claude', 'storage', 'node_modules', 'vendor', 'assets', '.venv', '__pycache__'}

def count_php_functions(content):
    # Match function definitions in PHP
    # function foo(...) or public/private/protected function foo(...)
    pattern = re.compile(r'(?:public\s+|private\s+|protected\s+|static\s+)*function\s+([a-zA-Z0-9_]+)\s*\(', re.IGNORECASE)
    matches = pattern.findall(content)
    # Filter out anonymous function / closures if regex accidentally matched
    return [m for m in matches if m.lower() not in ('function', '')]

def count_js_functions(content):
    # 1. Named functions: function foo(...)
    # 2. Variable assigned functions: const/let/var foo = function(...) or () =>
    # 3. Method definitions inside objects/classes: foo(...) {
    named_fn = re.findall(r'\bfunction\s+([a-zA-Z0-9_$]+)\s*\(', content)
    var_fn = re.findall(r'\b(?:const|let|var)\s+([a-zA-Z0-9_$]+)\s*=\s*(?:async\s*)?(?:function|\([^)]*\)\s*=>)', content)
    methods = re.findall(r'^\s*([a-zA-Z0-9_$]+)\s*\([^)]*\)\s*\{', content, re.MULTILINE)
    
    keywords = {'if', 'for', 'while', 'switch', 'catch', 'function', 'return', 'constructor', 'onAdd', 'onRemove'}
    all_funcs = []
    for f in named_fn + var_fn + methods:
        if f and f not in keywords and f not in all_funcs:
            all_funcs.append(f)
    return all_funcs

def count_py_functions(content):
    pattern = re.compile(r'^\s*def\s+([a-zA-Z0-9_]+)\s*\(', re.MULTILINE)
    return pattern.findall(content)

def count_html_functions(content):
    # JS functions inside html script tags
    return count_js_functions(content)

stats_by_ext = {}
total_files = 0
total_lines = 0
total_code = 0
total_blank = 0
total_comment = 0
total_funcs = 0

file_breakdown = []
all_functions_detail = []

for root, dirs, files in os.walk('.'):
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    for file in sorted(files):
        ext = os.path.splitext(file)[1].lower()
        if ext in extensions:
            filepath = os.path.normpath(os.path.join(root, file))
            try:
                with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
                    lines = content.splitlines()
            except Exception:
                continue

            file_lines = len(lines)
            file_blank = 0
            file_comment = 0
            file_code = 0

            in_multiline_comment = False

            for line in lines:
                stripped = line.strip()
                if not stripped:
                    file_blank += 1
                elif stripped.startswith('//') or stripped.startswith('#') or (stripped.startswith('<!--') and not stripped.startswith('<!--#')):
                    file_comment += 1
                elif stripped.startswith('/*') and stripped.endswith('*/'):
                    file_comment += 1
                elif stripped.startswith('/*'):
                    in_multiline_comment = True
                    file_comment += 1
                elif in_multiline_comment:
                    file_comment += 1
                    if '*/' in stripped:
                        in_multiline_comment = False
                else:
                    file_code += 1

            funcs_found = []
            if ext == '.php':
                funcs_found = count_php_functions(content)
            elif ext == '.js':
                funcs_found = count_js_functions(content)
            elif ext == '.py':
                funcs_found = count_py_functions(content)
            elif ext == '.html':
                funcs_found = count_html_functions(content)

            file_funcs = len(funcs_found)

            if ext not in stats_by_ext:
                stats_by_ext[ext] = {'files': 0, 'lines': 0, 'code': 0, 'blank': 0, 'comment': 0, 'funcs': 0}
            
            stats_by_ext[ext]['files'] += 1
            stats_by_ext[ext]['lines'] += file_lines
            stats_by_ext[ext]['code'] += file_code
            stats_by_ext[ext]['blank'] += file_blank
            stats_by_ext[ext]['comment'] += file_comment
            stats_by_ext[ext]['funcs'] += file_funcs

            total_files += 1
            total_lines += file_lines
            total_code += file_code
            total_blank += file_blank
            total_comment += file_comment
            total_funcs += file_funcs

            file_breakdown.append({
                'path': filepath,
                'ext': ext,
                'lines': file_lines,
                'code': file_code,
                'blank': file_blank,
                'comment': file_comment,
                'funcs': file_funcs
            })

            if file_funcs > 0:
                all_functions_detail.append((filepath, file_funcs, funcs_found))

print("=== CODEBASE SUMMARY BY EXTENSION ===")
print(f"{'Extension':<10} | {'Files':<6} | {'Total Lines':<12} | {'Code Lines':<12} | {'Blank':<8} | {'Comments':<9} | {'Functions':<10}")
print("-" * 80)
for ext, data in sorted(stats_by_ext.items(), key=lambda x: x[1]['lines'], reverse=True):
    print(f"{ext:<10} | {data['files']:<6} | {data['lines']:<12} | {data['code']:<12} | {data['blank']:<8} | {data['comment']:<9} | {data['funcs']:<10}")

print("=" * 80)
print(f"TOTAL FILES ANALYZED : {total_files}")
print(f"TOTAL LINES          : {total_lines}")
print(f"CODE LINES           : {total_code}")
print(f"BLANK LINES          : {total_blank}")
print(f"COMMENT LINES        : {total_comment}")
print(f"TOTAL FUNCTIONS      : {total_funcs}")

print("\n=== TOP 20 FILES BY LINE COUNT ===")
for fb in sorted(file_breakdown, key=lambda x: x['lines'], reverse=True)[:20]:
    print(f"{fb['path']:<45} | Total: {fb['lines']:<5} | Code: {fb['code']:<5} | Blank: {fb['blank']:<4} | Funcs: {fb['funcs']}")

print("\n=== DETAILED FUNCTION COUNTS PER FILE ===")
for path, count, fn_list in sorted(all_functions_detail, key=lambda x: x[1], reverse=True):
    fn_str = ", ".join(fn_list[:8])
    if len(fn_list) > 8:
        fn_str += f" (+{len(fn_list)-8} more)"
    print(f"{path:<45} ({count} funcs): {fn_str}")
