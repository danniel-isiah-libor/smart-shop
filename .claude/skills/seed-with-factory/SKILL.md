---
name: seed-with-factory
description: Add realistic fake data for a model. Use when asked to seed or add sample data.
argument-hint: [Model] [count]
---

Seed $0 with $1 records.

1. Make database/factories/$0Factory.php realistic
2. In DatabaseSeeder, add $0::factory($1)->create()
   after the models it depends on
3. Run php artisan migrate for new migrations
4. Run newly created seeder/s
5. Report how many $0 rows exist
