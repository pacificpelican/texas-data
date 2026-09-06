# Texas Regional Vault

Texas Regional Vault is a lightweight PHP app for organizing regional project files across Texas. It supports a local-first, region-based document workflow with optional MySQL persistence when configured, plus a local AI assistant for analyzing the contents of uploaded documents.

## Features

- Clickable Texas map divided into five regions
- Regional file uploads and quick file access
- Vault overview with paginated spreadsheet-style listing of all files
- Email signup and login
- Optional Google sign-in when configured in the app config
- Profile page with account details, upload history, and data-management controls
- Local AI assistant that summarizes or analyzes files from a selected region or the full vault
- General LLM chat page for free-form prompts, with a separate per-user chat history on the profile
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

## Local-first data behavior

The app prefers a JSON file store unless a valid MySQL connection is configured in config.php. This keeps the project portable and easy to run locally without a database server.

## AI assistant

The AI Assistant page can review the current vault documents and answer questions based on the selected region or the complete vault. It uses Ollama at localhost:11434 when enabled in the config.

## LLM chat

The LLM Chat page lets any signed-in user send free-form prompts to the same local Ollama model. Each prompt/response pair is saved per user (MySQL `llm_history` table, or data/llm-history.json in file-storage mode) and shown on the profile page in a separate "LLM chat history" section.

## Notes

- Google login is hidden automatically when the required OAuth settings are missing.
- The debug widget can be toggled from the top-right of the app pages for local troubleshooting.
- The app is designed to degrade gracefully when optional services are unavailable.


