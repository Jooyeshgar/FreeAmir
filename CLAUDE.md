# FreeAmir Project Guidelines

## Build & Development Commands
- `sail npm run dev` - Start development server (vite)
- `sail npm run build` - Build assets for production
- `sail up -d` - Serve the application
- `sail artisan test` - Run all tests
- `sail artisan test --filter=TestName` - Run specific test
- `./vendor/bin/phpunit --filter testMethodName tests/Feature/ExampleTest.php` - Run single test method
- `./vendor/bin/pint` - Format code with Laravel Pint

## Deployment Commands
- `envoy run deploy` - Deploy application
- `envoy run init` - Initialize deployment
- `envoy run rollback` - Rollback deployment

## Code Style Guidelines
- **Naming**: PascalCase for classes, camelCase for methods/variables, snake_case for database fields
- **Indentation**: 4 spaces (2 spaces for YAML files)
- **End of line**: LF (Unix-style)
- **Error handling**: Use custom exceptions and transaction blocks for multi-step operations
- **Method organization**: Follow RESTful controller conventions (index, create, store, show, edit, update, destroy)
- **Dependencies**: Use dependency injection in controllers, leverage Laravel service container
- **Documentation**: Use PHP DocBlocks with @param and @return tags for methods
- **Validation**: Handle input validation in controllers or dedicated service classes
- **Error responses**: Use redirects with flash messages for user feedback