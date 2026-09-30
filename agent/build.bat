@echo off
REM Builds dist\hazri-agent.exe (one file). Run on Windows with Python 3.11+.
python -m venv .venv
call .venv\Scripts\activate
pip install -r requirements.txt pyinstaller==6.*
pyinstaller --onefile --name hazri-agent --console hazri_agent\__main__.py
copy agent.example.yaml dist\agent.example.yaml
echo Built dist\hazri-agent.exe
