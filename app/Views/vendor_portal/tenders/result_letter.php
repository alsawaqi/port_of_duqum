<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc($letter->subject); ?></title>
<style>body{font:16px/1.7 Arial,sans-serif;color:#172b45;background:#f3f6fa;margin:0}main{max-width:760px;margin:36px auto;background:white;padding:48px;border:1px solid #dce3ec}h1{font-size:24px}p{white-space:pre-line}button{padding:10px 18px;cursor:pointer}small{color:#53657a}@media(max-width:600px){main{margin:0;padding:24px}}@media print{body{background:white}main{border:0;margin:0}button{display:none}}</style></head>
<body><main><strong>Port of Duqm · Procurement</strong><h1><?php echo esc($letter->subject); ?></h1>
<small>Issued: <?php echo esc($letter->published_at); ?></small><p><?php echo esc($letter->message); ?></p>
<button type="button" onclick="window.print()">Print / Save as PDF</button></main></body></html>
