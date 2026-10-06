# Texas Data

<p align="center">
  <svg width="84" height="84" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Texas Data longhorn logo">
    <defs>
      <linearGradient id="rainbow" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#e5484d"/>
        <stop offset="28%" stop-color="#f76b15"/>
        <stop offset="50%" stop-color="#ffc53d"/>
        <stop offset="68%" stop-color="#30a46c"/>
        <stop offset="86%" stop-color="#3e63dd"/>
        <stop offset="100%" stop-color="#8e4ec6"/>
      </linearGradient>
    </defs>
    <rect width="64" height="64" rx="18" fill="url(#rainbow)"/>
    <g transform="translate(6, 12) scale(0.8125)">
      <path fill="#ffffff" d="M32 12 C25 12 20 10 15 7 C9 4 5 3 3 6 C1 9 5 13 11 15 C16 17 21 18 25 19 L25 23 C25 26 26 29 28 31 L26 44 L38 44 L36 31 C38 29 39 26 39 23 L39 19 C43 18 48 17 53 15 C59 13 63 9 61 6 C59 3 55 4 49 7 C44 10 39 12 32 12 Z"/>
    </g>
  </svg>
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
php -S 127.0.0.1:8000
```

Then open:

```text
http://127.0.0.1:8000
```

A demo account is seeded on first run: `demo@texasdrive.app` / `demo123`.

## Local-first data behavior

The app prefers a JSON file store unless a valid MySQL connection is configured in config.php. This keeps the project portable and easy to run locally without a database server. Per-user settings live in data/user-settings.json; accounts without saved settings always see the built-in defaults.

## AI features

All AI features (AI Assistant, LLM Chat, Shake-speare Prediction Machine) send prompts to a local Ollama server at localhost:11434 when enabled in the `llm` section of config.php. Change the model there (default llama3.1:8b), and tune the default temperature — each prompt page also offers an optional per-request temperature override, which is recorded alongside each history entry.

## Shake-speare Prediction Machine

The source text is the Project Gutenberg edition of Hamlet (assets/Hamlet.md). Excerpts are capped at 1000 words; the start-word slider fetches a passage from any position in the play, preferring to end at a scene break when one falls within the 600–1000 word window. The continuation length can be set to roughly 1×, 1.5×, or 2× the excerpt.

## Notes

- Google login is hidden automatically when the required OAuth settings are missing.
- The debug widget can be toggled from the top-right of the app pages for local troubleshooting.
- The app is designed to degrade gracefully when optional services are unavailable.


