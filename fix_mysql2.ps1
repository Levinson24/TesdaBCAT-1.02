$ErrorActionPreference = "Stop"
Write-Host "Stopping any running mysqld processes..."
Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue

$dataFolder = "C:\xampp\mysql\data"
$corruptDataFolder = "C:\xampp\mysql\data_corrupt_20260313_101206"

Write-Host "Copying InnoDB log files to match ibdata1..."
Copy-Item -Path "$corruptDataFolder\ib_logfile0" -Destination "$dataFolder\ib_logfile0" -Force
Copy-Item -Path "$corruptDataFolder\ib_logfile1" -Destination "$dataFolder\ib_logfile1" -Force
Copy-Item -Path "$corruptDataFolder\ibdata1" -Destination "$dataFolder\ibdata1" -Force

Write-Host "Starting mysqld..."
Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini", "--standalone" -PassThru
