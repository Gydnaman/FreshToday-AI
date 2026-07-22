# FreshToday-AI Project Instructions

## Project objective

This repository is a graduation-design project. Prioritize completing demonstrable, end-to-end functionality over production-scale optimization, optional polish, broad abstraction, or unrelated refactoring.

## Iteration priorities

1. Each iteration must deliver the smallest working vertical slice that a reviewer can operate from the UI and verify against persisted data or an API response.
2. Implement must-have behavior before visual polish, performance tuning, extensibility, or speculative edge cases.
3. Reuse the current Laravel, Blade, Tailwind, JavaScript, Sanctum, and PHPUnit patterns unless a functional requirement cannot be met with them.
4. Keep tests focused on the core success path, access control, data correctness, and the most likely failure path. Do not expand test scope at the expense of completing the feature.
5. Do not weaken authentication, authorization, payment, order-state, inventory, or user-data protections in the name of speed.
6. Preserve the existing Simplified Chinese, Traditional Chinese, and English i18n structure for new user-visible copy.

## Definition of done

A feature iteration is complete only when its route and UI are reachable, its main interaction works, relevant data is displayed or persisted correctly, core automated tests pass, the full test suite remains green, and the feature can be demonstrated locally.

Document optional enhancements separately; they must not block the functional iteration.
