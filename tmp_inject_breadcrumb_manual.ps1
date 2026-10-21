$inc = "    @include('layouts.partials._breadcrumb_manual')"
Get-ChildItem -Path "$PSScriptRoot\resources\views" -Recurse -Filter '*.blade.php' |
    Where-Object { $_.FullName -notmatch 'vali-master' } |
    ForEach-Object {
        $c = [IO.File]::ReadAllText($_.FullName)
        if ($c -match 'app-breadcrumb breadcrumb' -and $c -notmatch '_breadcrumb_manual') {
            $n = [regex]::Replace($c, '(<ul class="app-breadcrumb breadcrumb">\s*\r?\n)', "`$1$inc`r`n", 1)
            if ($n -ne $c) {
                [IO.File]::WriteAllText($_.FullName, $n)
                Write-Output $_.FullName
            }
        }
    }
