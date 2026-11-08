---
name: create-model-with-policy
description: Create a model with migration, factory and policy using our conventions. Use for any new model or table.
argument-hint: [Model] [fields]
---

Create this model: $ARGUMENTS

1. Run php artisan make:model <Name> -mf --policy
2. Money is decimal(10,2); statuses are PHP enums
3. Add $fillable and typed relationships
4. Realistic fake() data in the factory
5. Run php artisan migrate, then php artisan test
