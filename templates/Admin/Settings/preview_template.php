<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vista Previa - <?= h($template->template_key) ?></title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .preview-header {
            background: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .preview-header h2 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 18px;
        }
        .preview-header p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        .email-preview {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="preview-header">
        <h2>Vista Previa: <?= h($template->template_key) ?></h2>
        <p>Esta es una vista previa con datos de ejemplo</p>
    </div>

    <div class="email-preview">
        <?= $previewBody ?>
    </div>
</body>
</html>
