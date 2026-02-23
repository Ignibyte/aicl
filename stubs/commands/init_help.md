# Forge Bootstrap — Project Initialization

This slash command initializes a new AICL project with CLAUDE.md, slash commands, and MCP configuration from the Forge server.

**Ship this file in the aicl/aicl package as `.claude/commands/init_help.md`** so new projects get `/init-help` out of the box.

---

You are helping the user bootstrap their AICL project with Forge. Walk through each step below. Do NOT auto-execute — confirm with the user before each action.

## Step 1: Detect Framework

Check `composer.json` for the framework:

```bash
grep -o '"laravel/framework"' composer.json && echo "Detected: Laravel"
grep -o '"drupal/core"' composer.json && echo "Detected: Drupal"
```

Confirm the detected framework with the user.

## Step 2: Register with Forge (if not already registered)

If `.mcp.json` already has a `forge` server entry, skip to Step 4.

Otherwise, ask the user for:
- **Forge URL** (default: `https://forge.ddev.site`)
- **Registration secret** (if required by the Forge instance)

Then register:

```bash
curl -s -X POST "${FORGE_URL}/api/v1/register" \
  -H "Content-Type: application/json" \
  -d '{"name": "<project_name>", "framework": "<framework>"}'
```

Save the returned API key — it is shown only once.

## Step 3: Write `.mcp.json`

Write the MCP configuration to `.mcp.json` in the project root:

```json
{
  "mcpServers": {
    "forge": {
      "url": "<forge_url>/mcp/forge",
      "headers": {
        "Authorization": "Bearer <api_key>"
      }
    }
  }
}
```

Add `.mcp.json` to `.gitignore` (it contains the API key).

**Tell the user to restart Claude Code** so the MCP server connection is loaded.

## Step 4: Download Bootstrap Files

After restart, call the `bootstrap-project` MCP tool:

```
bootstrap-project {}
```

This returns a file map with all CLAUDE.md and slash command files.

## Step 5: Write Files

For each file in the returned map, write it to the project root at the specified path:

- `CLAUDE.md` → project root
- `.claude/commands/*.md` → slash command directory

Confirm with the user before overwriting any existing files.

## Step 6: Verify

1. Check that `.claude/commands/` contains the expected slash commands
2. Check that `CLAUDE.md` exists and references Forge
3. Call `health` to verify the MCP connection
4. Call `bootstrap` to load project context

Done! The project is now fully connected to Forge with all slash commands and configuration.
