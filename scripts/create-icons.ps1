Add-Type -AssemblyName System.Drawing
$taskIconRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\backend\public\icons'))
New-Item -ItemType Directory -Path $taskIconRoot -Force | Out-Null
foreach ($taskIconSize in @(192,512)) {
    $taskBitmap = New-Object System.Drawing.Bitmap($taskIconSize, $taskIconSize)
    $taskGraphics = [System.Drawing.Graphics]::FromImage($taskBitmap)
    $taskGraphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $taskGraphics.Clear([System.Drawing.ColorTranslator]::FromHtml('#166b54'))
    $taskBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml('#f5f7f5'))
    $taskDiameter = [int]($taskIconSize * 0.24)
    foreach ($taskPoint in @(@(0.29,0.29),@(0.47,0.29),@(0.29,0.47),@(0.47,0.47))) {
        $taskGraphics.FillEllipse($taskBrush, [int]($taskIconSize*$taskPoint[0]), [int]($taskIconSize*$taskPoint[1]), $taskDiameter, $taskDiameter)
    }
    $taskPen = New-Object System.Drawing.Pen($taskBrush,[int]($taskIconSize*0.025))
    $taskGraphics.DrawLine($taskPen,[int]($taskIconSize*0.5),[int]($taskIconSize*0.55),[int]($taskIconSize*0.57),[int]($taskIconSize*0.73))
    $taskBitmap.Save((Join-Path $taskIconRoot "icon-$taskIconSize.png"),[System.Drawing.Imaging.ImageFormat]::Png)
    $taskGraphics.Dispose(); $taskBitmap.Dispose(); $taskBrush.Dispose(); $taskPen.Dispose()
}
