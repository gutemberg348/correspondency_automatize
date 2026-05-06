$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.Drawing

$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$IconRoot = Join-Path $ProjectRoot 'res\icon\android'
New-Item -ItemType Directory -Force -Path $IconRoot | Out-Null

function New-RoundedRectPath {
    param(
        [float] $X,
        [float] $Y,
        [float] $Width,
        [float] $Height,
        [float] $Radius
    )

    $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $diameter = $Radius * 2
    $path.AddArc($X, $Y, $diameter, $diameter, 180, 90)
    $path.AddArc($X + $Width - $diameter, $Y, $diameter, $diameter, 270, 90)
    $path.AddArc($X + $Width - $diameter, $Y + $Height - $diameter, $diameter, $diameter, 0, 90)
    $path.AddArc($X, $Y + $Height - $diameter, $diameter, $diameter, 90, 90)
    $path.CloseFigure()

    return $path
}

function New-Color {
    param([int] $Alpha, [int] $Red, [int] $Green, [int] $Blue)
    return [System.Drawing.Color]::FromArgb($Alpha, $Red, $Green, $Blue)
}

function Draw-BlueBackground {
    param(
        [System.Drawing.Graphics] $Graphics,
        [int] $Size
    )

    $rect = [System.Drawing.RectangleF]::new(0, 0, $Size, $Size)
    $brush = [System.Drawing.Drawing2D.LinearGradientBrush]::new(
        $rect,
        [System.Drawing.Color]::FromArgb(255, 45, 139, 247),
        [System.Drawing.Color]::FromArgb(255, 18, 101, 220),
        45
    )
    $Graphics.FillRectangle($brush, $rect)
    $brush.Dispose()
}

function Draw-GlassIcon {
    param(
        [System.Drawing.Graphics] $Graphics,
        [int] $Size,
        [bool] $IncludeBackground,
        [bool] $Monochrome
    )

    if ($IncludeBackground) {
        $outerPath = New-RoundedRectPath 0 0 $Size $Size ($Size * 0.17)
        $bgBrush = [System.Drawing.Drawing2D.LinearGradientBrush]::new(
            [System.Drawing.RectangleF]::new(0, 0, $Size, $Size),
            [System.Drawing.Color]::FromArgb(255, 44, 138, 246),
            [System.Drawing.Color]::FromArgb(255, 18, 101, 220),
            45
        )
        $Graphics.FillPath($bgBrush, $outerPath)
        $bgBrush.Dispose()
        $outerPath.Dispose()
    }

    $centerX = $Size * 0.5
    $centerY = $Size * 0.5

    if (-not $Monochrome) {
        $outerGlass = New-RoundedRectPath ($Size * 0.18) ($Size * 0.17) ($Size * 0.64) ($Size * 0.66) ($Size * 0.16)
        $innerGlass = New-RoundedRectPath ($Size * 0.24) ($Size * 0.23) ($Size * 0.52) ($Size * 0.56) ($Size * 0.13)
        $outerBrush = [System.Drawing.SolidBrush]::new((New-Color 94 230 244 255))
        $innerBrush = [System.Drawing.SolidBrush]::new((New-Color 74 238 248 255))
        $outerPen = [System.Drawing.Pen]::new((New-Color 170 235 248 255), [Math]::Max(1, $Size * 0.012))
        $innerPen = [System.Drawing.Pen]::new((New-Color 205 255 255 255), [Math]::Max(1, $Size * 0.009))

        $Graphics.FillPath($outerBrush, $outerGlass)
        $Graphics.DrawPath($outerPen, $outerGlass)
        $Graphics.FillPath($innerBrush, $innerGlass)
        $Graphics.DrawPath($innerPen, $innerGlass)

        $outerGlass.Dispose()
        $innerGlass.Dispose()
        $outerBrush.Dispose()
        $innerBrush.Dispose()
        $outerPen.Dispose()
        $innerPen.Dispose()
    }

    $fish = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $fish.AddBezier(
        [System.Drawing.PointF]::new($Size * 0.31, $centerY),
        [System.Drawing.PointF]::new($Size * 0.42, $Size * 0.39),
        [System.Drawing.PointF]::new($Size * 0.59, $Size * 0.39),
        [System.Drawing.PointF]::new($Size * 0.70, $centerY)
    )
    $fish.AddBezier(
        [System.Drawing.PointF]::new($Size * 0.70, $centerY),
        [System.Drawing.PointF]::new($Size * 0.59, $Size * 0.61),
        [System.Drawing.PointF]::new($Size * 0.42, $Size * 0.61),
        [System.Drawing.PointF]::new($Size * 0.31, $centerY)
    )

    $penWidth = [Math]::Max(2, $Size * 0.035)
    $fishPen = [System.Drawing.Pen]::new([System.Drawing.Color]::White, $penWidth)
    $fishPen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $fishPen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $fishPen.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $Graphics.DrawPath($fishPen, $fish)

    $tailPen = [System.Drawing.Pen]::new([System.Drawing.Color]::White, $penWidth)
    $tailPen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $tailPen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $tailPen.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $Graphics.DrawLine($tailPen, ($Size * 0.69), $centerY, ($Size * 0.82), ($Size * 0.38))
    $Graphics.DrawLine($tailPen, ($Size * 0.69), $centerY, ($Size * 0.82), ($Size * 0.62))

    $fish.Dispose()
    $fishPen.Dispose()
    $tailPen.Dispose()
}

function Save-Png {
    param(
        [string] $Path,
        [int] $Size,
        [scriptblock] $Draw
    )

    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.Clear([System.Drawing.Color]::Transparent)

    & $Draw $graphics $Size

    $bitmap.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $graphics.Dispose()
    $bitmap.Dispose()
}

$densities = [ordered]@{
    ldpi = @{ legacy = 36; adaptive = 81 }
    mdpi = @{ legacy = 48; adaptive = 108 }
    hdpi = @{ legacy = 72; adaptive = 162 }
    xhdpi = @{ legacy = 96; adaptive = 216 }
    xxhdpi = @{ legacy = 144; adaptive = 324 }
    xxxhdpi = @{ legacy = 192; adaptive = 432 }
}

foreach ($density in $densities.Keys) {
    $legacySize = $densities[$density].legacy
    $adaptiveSize = $densities[$density].adaptive

    Save-Png -Path (Join-Path $IconRoot "$density.png") -Size $legacySize -Draw {
        param($g, $s)
        Draw-GlassIcon -Graphics $g -Size $s -IncludeBackground $true -Monochrome $false
    }

    Save-Png -Path (Join-Path $IconRoot "$density-background.png") -Size $adaptiveSize -Draw {
        param($g, $s)
        Draw-BlueBackground -Graphics $g -Size $s
    }

    Save-Png -Path (Join-Path $IconRoot "$density-foreground.png") -Size $adaptiveSize -Draw {
        param($g, $s)
        Draw-GlassIcon -Graphics $g -Size $s -IncludeBackground $false -Monochrome $false
    }

    Save-Png -Path (Join-Path $IconRoot "$density-monochrome.png") -Size $adaptiveSize -Draw {
        param($g, $s)
        Draw-GlassIcon -Graphics $g -Size $s -IncludeBackground $false -Monochrome $true
    }
}

Write-Host "Android icons generated in $IconRoot"
