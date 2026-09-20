# TOUCHnRELIEF — Full Database ERD

> Derived from Laravel migrations. **PK** = Primary Key · **FK** = Foreign Key (DB constraint) · **UK** = Unique Key · **IDX** = Index only · **LOG** = Logical link (no DB FK)

---

## 1. users
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| name | string | |
| username | string(30) nullable | **UK** |
| email | string | **UK** |
| email_verified_at | timestamp nullable | |
| contact_number | string(32) nullable | |
| department | string nullable | |
| job_title | string nullable | |
| location | string nullable | |
| profile_photo_path | string nullable | |
| birthday | date nullable | |
| sex | string(16) nullable | |
| therapist_gender_preference | string(24) nullable | |
| is_pregnant | boolean nullable | |
| pressure_preference | string(16) nullable | |
| profile_completed_at | timestamp nullable | |
| password | string | |
| role | string(20) default user | |
| is_walk_in | boolean default false | **IDX** |
| remember_token | string nullable | |
| created_at, updated_at | timestamps | |

---

## 2. password_reset_tokens
| Column | Type | Key |
|--------|------|-----|
| email | string | **PK** |
| token | string | |
| created_at | timestamp nullable | |

---

## 3. sessions
| Column | Type | Key |
|--------|------|-----|
| id | string | **PK** |
| user_id | bigint nullable | **IDX** (→ users.id, no FK constraint) |
| ip_address | string(45) nullable | |
| user_agent | text nullable | |
| payload | longtext | |
| last_activity | integer | **IDX** |

---

## 4. registrations
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| user_id | bigint | **FK → users.id** CASCADE |
| name | string | |
| username | string(30) | |
| email | string | |
| contact_number | string(32) | |
| created_at, updated_at | timestamps | **IDX** created_at |

**Relationship:** users 1 — N registrations

---

## 5. user_medications
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| user_id | bigint | **FK → users.id** CASCADE |
| name | string | |
| sort_order | smallint default 0 | |
| created_at, updated_at | timestamps | |

**Relationship:** users 1 — N user_medications

---

## 6. customers
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| customer_id | string | **UK** |
| full_name | string | |
| birthday | date nullable | |
| number | string(30) nullable | |
| email | string | **UK** |
| password | string | |
| created_at, updated_at | timestamps | |

**LOG:** email ≈ users.email (app-level link, no FK)

---

## 7. receptionists
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| receptionist_id | string | **UK** |
| full_name | string nullable | |
| username | string | **UK** |
| email | string | **UK** |
| address | string nullable | |
| phone_number | string(30) nullable | |
| birthday | date nullable | |
| shift | string | |
| profile_picture | string nullable | |
| created_at, updated_at | timestamps | |

**LOG:** email ≈ users.email (staff login link, no FK)

---

## 8. therapists
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| therapist_code | string | **UK** |
| name | string | |
| role | string nullable | |
| bio | text nullable | |
| contact_number | string(30) nullable | |
| address | string nullable | |
| email | string nullable | |
| birthday | date nullable | |
| avatar_initials | string(4) nullable | |
| photo_url | string nullable | |
| landing_photo | string nullable | |
| specializations | json nullable | |
| certifications | json nullable | |
| sessions_label | string(30) nullable | |
| accent_color | string(20) nullable | |
| status | string(20) default available | |
| working_days | json nullable | |
| day_off_until | date nullable | |
| work_on_off_day | boolean default false | |
| total_hours | smallint default 0 | |
| rating | decimal(3,1) default 0 | |
| service_hours_pct | tinyint default 0 | |
| is_active | boolean default true | |
| sort_order | smallint default 0 | |
| created_at, updated_at | timestamps | |

**Relationship:** therapists 1 — N transactions (via therapist_id, **LOG** only)

---

## 9. spa_services
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| name | string | **UK** |
| price_amount | decimal(10,2) | |
| duration_minutes | smallint | |
| best_for | string | |
| description | text | |
| image | string nullable | |
| prenatal_only | boolean default false | |
| is_active | boolean default true | |
| created_at, updated_at | timestamps | |

**LOG:** name ← spa_bookings.service_name, service_time_slots.service_name

---

## 10. spa_bookings
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| user_id | bigint | **FK → users.id** CASCADE |
| client_name | string nullable | **IDX** |
| booking_source | string(20) default online | **IDX** |
| service_name | string | **LOG → spa_services.name** |
| therapist_name | string nullable | **LOG → therapists.name** |
| booking_date | date | |
| time_slot | string(30) | |
| duration_minutes | smallint nullable | |
| amount | decimal(10,2) nullable | |
| notes | text nullable | |
| cancelled_at | timestamp nullable | |
| cancellation_reason | string(500) nullable | |
| rescheduled_at | timestamp nullable | |
| rescheduled_from_date | date nullable | |
| rescheduled_from_time_slot | string(30) nullable | |
| completed_at | timestamp nullable | |
| session_started_at | timestamp nullable | |
| session_status | string(20) default confirmed | |
| created_at, updated_at | timestamps | |

**Relationships:**
- users 1 — N spa_bookings
- spa_bookings 1 — 0..1 transactions
- spa_bookings 1 — N customer_notifications

---

## 11. transactions
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| spa_booking_id | bigint nullable | **FK → spa_bookings.id** NULL ON DELETE |
| transaction_id | string | **UK** |
| client_name | string | |
| user_id | bigint nullable | **FK → users.id** NULL ON DELETE |
| therapist_id | bigint | **LOG → therapists.id** |
| service_name | string | |
| date | date | |
| time | time | |
| duration | integer | |
| amount | decimal(10,2) | |
| notes | text nullable | |
| created_at, updated_at | timestamps | |

---

## 12. customer_notifications
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| user_id | bigint | **FK → users.id** CASCADE |
| spa_booking_id | bigint nullable | **FK → spa_bookings.id** NULL ON DELETE |
| type | string(30) | |
| title | string | |
| message | text | |
| details | json nullable | |
| dedup_key | string nullable | **UK** (user_id, dedup_key) |
| read_at | timestamp nullable | |
| created_at, updated_at | timestamps | **IDX** (user_id, read_at, created_at) |

---

## 13. time_slots
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| label | string(30) | **UK** |
| sort_order | smallint default 0 | |
| is_active | boolean default true | |
| is_custom | boolean default false | |
| created_at, updated_at | timestamps | |

---

## 14. service_time_slots
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| service_name | string | **UK** (service_name, time_slot_id) · **LOG → spa_services.name** |
| time_slot_id | bigint | **FK → time_slots.id** CASCADE |
| created_at, updated_at | timestamps | |

---

## 15. service_slot_date_overrides
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| service_name | string | **UK** (service_name, slot_date, time_slot_id) |
| slot_date | date | |
| time_slot_id | bigint | **FK → time_slots.id** CASCADE |
| is_enabled | boolean default false | |
| created_at, updated_at | timestamps | |

---

## 16. store_closures
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| closure_date | date | **UK** |
| note | string nullable | |
| created_at, updated_at | timestamps | |

---

## 17. activity_logs
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| user_id | bigint nullable | **FK → users.id** NULL ON DELETE |
| user_role | string(32) | **IDX** (user_role, created_at) |
| user_name | string | |
| action | string(64) | **IDX** (action, created_at) |
| subject_type | string nullable | polymorphic |
| subject_id | bigint nullable | polymorphic |
| description | text | |
| properties | json nullable | |
| ip_address | string(45) nullable | |
| user_agent | string nullable | |
| created_at | timestamp | **IDX** (user_id, created_at) |

---

## 18. site_settings
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| key | string | **UK** |
| value | text nullable | |
| created_at, updated_at | timestamps | |

---

## 19. cache
| Column | Type | Key |
|--------|------|-----|
| key | string | **PK** |
| value | mediumtext | |
| expiration | integer | **IDX** |

---

## 20. cache_locks
| Column | Type | Key |
|--------|------|-----|
| key | string | **PK** |
| owner | string | |
| expiration | integer | **IDX** |

---

## 21. jobs
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| queue | string | **IDX** |
| payload | longtext | |
| attempts | tinyint | |
| reserved_at | integer nullable | |
| available_at | integer | |
| created_at | integer | |

---

## 22. job_batches
| Column | Type | Key |
|--------|------|-----|
| id | string | **PK** |
| name | string | |
| total_jobs | integer | |
| pending_jobs | integer | |
| failed_jobs | integer | |
| failed_job_ids | longtext | |
| options | mediumtext nullable | |
| cancelled_at | integer nullable | |
| created_at | integer | |
| finished_at | integer nullable | |

---

## 23. failed_jobs
| Column | Type | Key |
|--------|------|-----|
| id | bigint | **PK** |
| uuid | string | **UK** |
| connection | text | |
| queue | text | |
| payload | longtext | |
| exception | longtext | |
| failed_at | timestamp | |

---

## Relationship Summary

```
users ──< registrations
users ──< user_medications
users ──< spa_bookings
users ──< transactions
users ──< activity_logs
users ──< customer_notifications
spa_bookings ──o transactions
spa_bookings ──< customer_notifications
time_slots ──< service_time_slots
time_slots ──< service_slot_date_overrides
customers ··· users (email)
receptionists ··· users (email)
spa_services ··· spa_bookings (service_name)
spa_services ··· service_time_slots (service_name)
therapists ··· transactions (therapist_id)
therapists ··· spa_bookings (therapist_name)
```

Legend: ──< one-to-many FK · ──o zero-or-one FK · ··· logical link
