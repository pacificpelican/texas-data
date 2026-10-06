# Texas Data

<p align="center">
  <img src="assets/logo.svg" alt="Texas Data longhorn logo" width="84" height="84" />
</p>

Texas Data is a data transformation platform from [Alta Redwood](https://altaredwood.work) — a lightweight PHP app for organizing project files by region and running local-LLM workflows against them. It is local-first: JSON file storage by default, with optional MySQL persistence when configured, and all AI features run against a local Ollama server (no cloud calls, no API keys).

## Features

- Clickable Texas map dashboard divided into five regions
- Regional file uploads and quick file access
- Vault overview with paginated spreadsheet-style listing of all files
- Email signup and login, with optional Google sign-in (hidden automatically unless configured)
- Profile page with account details, upload history, AI/LLM history, and data-management controls
- Settings page:
  - Display settings with per-user light/dark mode
  - Per-user region settings — rename the five regions and set each region's default AI Assistant query
  - LLM info panel showing the active model, endpoint, and Ollama notes
- AI Assistant that summarizes or analyzes files from a selected region or the full vault
- LLM Chat page for free-form prompts, with an optional temperature override and per-user history
- Shake-speare Prediction Machine — paste a passage (or dial in a start word to pull ~1000 words from the bundled Hamlet text, cutting at scene breaks) and the model continues the story; runs are saved per user with their own history pages
- Printable permalink pages for LLM chats and Shake-speare predictions
- Flexible storage mode:
  - default JSON file storage for simple local use
  - MySQL/MariaDB when enabled and configured with the correct database credentials

## Run locally

From the project directory:

```bash
php -S 127.0.0.1:8000 router.php
```

Then open:

```text
http://127.0.0.1:8000
```

A demo account is seeded on first run: `demo@texasdrive.app` / `demo123`.

## Local-first data behavior

The app prefers a JSON file store unless a valid MySQL connection is configured in [config.php](./config.php). This keeps the project portable and easy to run locally without a database server. Per-user settings live in data/user-settings.json; accounts without saved settings always see the built-in defaults.

## AI features

All AI features (AI Assistant, LLM Chat, Shake-speare Prediction Machine) send prompts to a local [Ollama](https://ollama.com/) server at localhost:11434 when enabled in the `llm` section of config.php. Change the model there (default llama3.1:8b), and tune the default temperature — each prompt page also offers an optional per-request temperature override, which is recorded alongside each history entry.

### Setting up Ollama

1. Install Ollama from [ollama.com/download](https://ollama.com/download) (Windows, macOS, and Linux builds available).  Ollama may also be available to be installed by package managers like [Chocolatey](https://community.chocolatey.org/packages/Ollama) or [Homebrew](https://formulae.brew.sh/formula/ollama) or [Snap](https://snapcraft.io/install/ollama/ubuntu).
2. Download the model the app expects:

```bash
ollama pull llama3.1:8b
```

3. Start the server (it usually runs automatically after install, but this works everywhere):

```bash
ollama serve
```

The API listens on `http://localhost:11434` by default, which matches `config.php` out of the box.

Useful day-to-day commands:

```bash
ollama list            # show downloaded models
ollama run llama3.1:8b # quick chat test in the terminal
ollama rm llama3.1:8b  # remove a model to free disk space
ollama ps              # show which models are currently loaded
```

To try a different model, pull it and update the `model` value in the `llm` section of config.php — no other code changes needed. Larger models (e.g. llama3.1:70b) produce noticeably better results but need far more RAM/VRAM; the 8b default is a good balance for a local machine.

## Shake-speare Prediction Machine

The source text is the [Project Gutenberg edition of *Hamlet*](https://www.gutenberg.org/ebooks/1524?msg=welcome_stranger) ([assets/Hamlet.md](./assets/Hamlet.md)). Excerpts are capped at 1000 words; the start-word slider fetches a passage from any position in the play, preferring to end at a scene break when one falls within the 600–1000 word window. The continuation length can be set to roughly 1×, 1.5×, or 2× the excerpt.

## Notes

- Google login is hidden automatically when the required OAuth settings are missing.
- The debug widget can be toggled from the top-right of the app pages for local troubleshooting.
- The app is designed to degrade gracefully when optional services are unavailable.


