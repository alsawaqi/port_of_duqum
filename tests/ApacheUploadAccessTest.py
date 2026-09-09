from pathlib import Path
import socket, subprocess, time, urllib.request, urllib.error, json
root=Path(__file__).resolve().parents[1]
import tempfile, uuid
out=Path(tempfile.gettempdir()) / ('pod-upload-read-check-' + uuid.uuid4().hex)
out.mkdir(exist_ok=True)
site=out/'site'; site.mkdir(exist_ok=True)
# Minimal inherited child rule mirrors the production upload directory.
child=b'<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^index.php$ - [L]\n</IfModule>\n'
for rel in ['files/system','files/profile_images','files/general','files/temp','files/tender_files','assets/images','system','app','writable/uploads','documentation']:
    (site/rel).mkdir(parents=True,exist_ok=True)
    (site/rel/'test.png').write_bytes((root/'assets/images/port-duqum-signin-logo.png').read_bytes())
    (site/rel/'test.php').write_text('DUMMY EXECUTABLE SHOULD NOT BE SERVED')
for rel in ['files','system','writable']:
    (site/rel/'.htaccess').write_bytes(child)
(site/'.env').write_text('DUMMY PRIVATE VALUE')
(site/'files/general/test.pdf').write_bytes(b'%PDF-1.4\n%%EOF')
(site/'files/general/test.sql').write_text('DUMMY DATABASE DUMP')
(site/'index.php').write_text('DUMMY FRONT CONTROLLER')
with socket.socket() as s:
    s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
conf=out/'httpd.conf'
lines=['ServerRoot "C:/xampp/apache"',f'Listen 127.0.0.1:{port}',f'ServerName localhost:{port}',f'PidFile "{(out/"httpd.pid").as_posix()}"',f'ErrorLog "{(out/"error.log").as_posix()}"','LogLevel warn','ThreadsPerChild 8']
for module in ['authz_core','authz_host','access_compat','mime','headers','rewrite','dir']:
    lines.append(f'LoadModule {module}_module modules/mod_{module}.so')
lines += ['TypesConfig "C:/xampp/apache/conf/mime.types"',f'DocumentRoot "{site.as_posix()}"',f'<Directory "{site.as_posix()}">','AllowOverride All','Require all granted','Options FollowSymLinks','</Directory>']
conf.write_text('\n'.join(lines)+'\n')
old=(root/'.htaccess').read_text().replace('    # Inherited rules see a child-relative path (e.g. system/logo.png inside\n    # files/). Match the full URI to protect the root system/ code directory\n    # without blocking the public files/system/ image directory.\n    RewriteCond %{REQUEST_URI} ^/(?:app|system|tests|writable|updates|documentation|vendor|node_modules|\\.git|\\.svn)(?:/|$) [NC]\n    RewriteRule ^ - [F,L]', '    RewriteRule ^(?:app|system|tests|writable|updates|documentation|vendor|node_modules|\\.git|\\.svn)(?:/|$) - [F,L,NC]')
(site/'.htaccess').write_text(old)
cmd=[r'C:\xampp\apache\bin\httpd.exe','-f',str(conf)]
subprocess.run(cmd+['-t'],check=True,capture_output=True)
proc=subprocess.Popen(cmd+['-X'],creationflags=subprocess.CREATE_NO_WINDOW,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
def status(path):
    try:
        with urllib.request.urlopen(f'http://127.0.0.1:{port}/'+path,timeout=5) as response: return response.status
    except urllib.error.HTTPError as error: return error.code
try:
    for _ in range(30):
        try: before=status('files/system/test.png'); break
        except urllib.error.URLError: time.sleep(.1)
    else: raise RuntimeError('Isolated Apache did not start')
    assert before==403, f'Expected reproduced 403, got {before}'
    (site/'.htaccess').write_bytes((root/'.htaccess').read_bytes())
    cases={'files/system/test.png':200,'files/profile_images/test.png':200,'files/general/test.pdf':200,'assets/images/test.png':200,'system/test.png':403,'app/test.png':403,'writable/uploads/test.png':403,'documentation/test.png':403,'files/system/test.php':403,'files/general/test.sql':403,'files/temp/test.png':403,'files/tender_files/test.png':403,'.env':403}
    results={name:status(name) for name in cases}
    assert results==cases, json.dumps(results)
    report={'before_logo_status':before,'after':results,'isolated_port':port,'checks_passed':len(cases)}
    (out/'results.json').write_text(json.dumps(report,indent=2))
    print(json.dumps(report))
finally:
    proc.terminate(); proc.wait(timeout=10)
