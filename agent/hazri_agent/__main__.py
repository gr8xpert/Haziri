"""Entry point: `python -m hazri_agent -c agent.yaml [--once]` (also the PyInstaller entry)."""

from __future__ import annotations

import argparse
import logging
import logging.handlers
import sys

import yaml

from .agent import Agent, AgentConfig, __version__


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="hazri-agent", description="Hazri ZKTeco bridge agent")
    parser.add_argument("-c", "--config", default="agent.yaml")
    parser.add_argument("--once", action="store_true", help="poll once and exit (for testing / Task Scheduler)")
    parser.add_argument("--log", default="hazri-agent.log")
    parser.add_argument("--version", action="version", version=__version__)
    args = parser.parse_args(argv)

    handlers: list[logging.Handler] = [logging.StreamHandler(sys.stdout),
                                       logging.handlers.RotatingFileHandler(args.log, maxBytes=2_000_000, backupCount=5)]
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s", handlers=handlers)

    with open(args.config, encoding="utf-8") as fh:
        config = AgentConfig.from_dict(yaml.safe_load(fh) or {})

    agent = Agent(config)
    if args.once:
        health = agent.poll_once()
        return 0 if all(h["reachable"] and not h["error"] for h in health) else 1
    agent.run_forever()
    return 0


if __name__ == "__main__":
    sys.exit(main())
