<p align="center">
  <img src="art/memry-logo.png" alt="memry" width="400">
</p>

# memry-server

Version **0.15.3** · [Changelog](CHANGELOG.md)

This is the server behind memry: a hosted, persistent memory MCP server for AI agents. Agents save and recall knowledge (decisions, bug fixes, conventions, session summaries) across sessions and projects.

## Using memry

You do not need to run this server to use memry. Install the [memry CLI](https://github.com/mrtheroi/memry-cli), then let it sign you in and connect your agent:

```bash
brew install mrtheroi/tap/memry
memry setup
```

## Development

Local setup, tests, configuration, deployment and the rest of the developer reference start at [docs/development.md](docs/development.md).

## License

memry-server is released under the [MIT license](LICENSE).
