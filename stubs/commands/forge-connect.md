# Forge Connect — Self-Service Project Registration

Copy this file to your project's `.claude/commands/forge-connect.md` to enable the `/forge-connect` slash command.

---

You are helping the user register their project with a Forge RLM server and configure the MCP connection.

## Step 1: Gather Information

Ask the user the following questions (use AskUserQuestion tool if available, otherwise ask directly):

1. **Project name** — What is the name of this project? (e.g. "My Laravel App")
2. **Framework** — Which framework does this project use? Options: laravel, django, rails, nextjs, nuxt, flask, express, drupal, other
3. **Description** (optional) — A short description of the project

If the framework is obvious from the codebase (e.g., `composer.json` has `laravel/framework`), auto-detect it and confirm with the user.

## Step 2: Register with Forge

Set the Forge URL. If not already configured, ask the user or check for a `FORGE_URL` environment variable:

```bash
FORGE_URL="${FORGE_URL:-https://forge.ddev.site}"
```

Call the registration endpoint:

```bash
curl -s -X POST "${FORGE_URL}/api/v1/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "<project_name>",
    "framework": "<framework>",
    "description": "<description>"
  }'
```

If a registration secret is required, include it:
```bash
  -H "Authorization: Bearer <registration_secret>"
```

## Step 3: Configure MCP

Take the `mcp_config` from the response and write it to `.mcp.json` in the project root. If `.mcp.json` already exists, merge the `forge` server entry into the existing `mcpServers` object.

Example `.mcp.json`:
```json
{
  "mcpServers": {
    "forge": {
      "url": "https://forge.ddev.site/mcp/forge",
      "headers": {
        "Authorization": "Bearer forge_pk_..."
      }
    }
  }
}
```

## Step 4: Verify Connection

After writing the MCP config, tell the user to restart their Claude Code session so the MCP server is loaded. Then they can verify by calling the `health` MCP tool.

## Important Notes

- The API key is shown **only once** during registration. Store it securely.
- If the key is lost, use the admin panel or `POST /api/v1/projects/rotate-key` (with the current key as bearer token) to generate a new one.
- The Forge MCP server provides tools for knowledge management, ticket tracking, architecture decisions, and more. Run `bootstrap` after connecting to see your project context.
