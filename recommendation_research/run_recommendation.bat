@echo off
rem Chay lai goi y cho toan bo nguoi dung. Dung voi Windows Task Scheduler.
rem Thu tu uu tien Python: bien CATBOOK_PYTHON, file python_path.txt, lenh "python" trong PATH.
cd /d "%~dp0"

set "PYTHON_EXE=%CATBOOK_PYTHON%"
if "%PYTHON_EXE%"=="" if exist python_path.txt set /p PYTHON_EXE=<python_path.txt
if "%PYTHON_EXE%"=="" set "PYTHON_EXE=python"

:: Ghi log thời điểm bắt đầu chạy
echo [%date% %time%] bat dau >> run_offline_recommendation.log

:: Chạy script Python và ghi dồn log
"%PYTHON_EXE%" run_offline_recommendation.py %* >> run_offline_recommendation.log 2>&1
