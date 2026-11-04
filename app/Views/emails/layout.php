<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($subject) ?></title>
<style>
body{margin:0;background:#f5f7fb;font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#18263d}
.wrap{max-width:600px;margin:0 auto;padding:24px 12px}
.head{background:#112443;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;font-weight:800;font-size:20px}
.head small{display:block;font-size:9px;letter-spacing:1.3px;font-weight:500;color:#aabbd4;margin-top:4px}
.body{background:#fff;border:1px solid #e5ebf3;border-top:0;padding:24px;border-radius:0 0 12px 12px;font-size:14px;line-height:1.6}
.button{display:inline-block;background:#2563eb;color:#fff!important;text-decoration:none;padding:11px 18px;border-radius:8px;font-weight:700;font-size:13px}
table.info{border-collapse:collapse;width:100%;margin:12px 0;font-size:13px}
table.info td{padding:8px 10px;border-bottom:1px solid #eef2f7}
table.info td:first-child{color:#728198;width:45%}
blockquote{margin:12px 0;padding:12px 14px;background:#f8fafc;border-left:3px solid #2563eb;border-radius:6px}
.foot{text-align:center;font-size:11px;color:#728198;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">◈ HostFlow<small><?= e(mb_strtoupper((string) $business)) ?></small></div>
  <div class="body"><?= $content ?></div>
  <div class="foot"><?= e($business) ?> · <?= e(setting('business_website')) ?><br><?= e(setting('business_email')) ?></div>
</div>
</body>
</html>
