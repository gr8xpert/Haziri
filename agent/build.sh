#!/usr/bin/env sh
# Builds dist/hazri-agent for Linux / Raspberry Pi (run on the target architecture).
set -e
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt 'pyinstaller==6.*'
pyinstaller --onefile --name hazri-agent run_agent.py
cp agent.example.yaml dist/
echo "Built dist/hazri-agent"
