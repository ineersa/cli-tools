# cli-tools

### Create sqlite database

bin/console --env=test doctrine:database:create

```bash
php -d xdebug.mode=debug -d xdebug.client_host=127.0.0.1 -d xdebug.client_port=9003 -d xdebug.start_with_request=yes ./bin/console ai:client
```


## Roadmap
 - Terminal UI interface 
 - Basic UI commands implementation
 - Implement projects system, connect database to store chats etc.
 - Settings inside project directory
 - OpenAI compatible models for chat, test with just llama.cpp and LiteLLM
 - Implement model switching between small and big models
 - MCP tools calling, implement in chat first, MCP tools filtering
 - Docker and docker-compose
 - Add basic tools as built-in tools, like Python and browser
 - Pre hooks, will be used to prepare context
 - Context preparation, add files addition to context
 - Context preparation, implement context enrichment by prompt intentions
 - Implement Plan mode, allow plan editing, storing and so on.
 - Execution mode implementation
 - Per task/plan execution
 - Post hooks, run tests/static analysis etc.
 - Knowledge graphs for documentation/code
