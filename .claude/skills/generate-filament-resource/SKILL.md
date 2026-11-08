---
name: generate-filament-resource
description: Create a Filament 5 admin resource for an existing Eloquent model. Use when asked for an admin page, CRUD screen or Filament resource.
argument-hint: [ModelName]
---

Create a Filament 5 resource for the $ARGUMENTS model.

1. Run: php artisan make:filament-resource $ARGUMENTS --generate --view
2. Form: Select with ->relationship() for every foreign key
3. Table: searchable, sortable name columns; prices with ->money('PHP')
4. Add a SelectFilter for every belongsTo relationship
5. Follow the conventions in CLAUDE.md
6. Run php artisan test and vendor/bin/pint, then list the files you changed
