"""PyInstaller entry: runs the package's main() (the package itself uses relative imports, so it can't be the script)."""

from hazri_agent.__main__ import main

if __name__ == "__main__":
    raise SystemExit(main())
