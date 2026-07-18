# Mobile Acceptance Results (Stage 10.4)

**Date:** 2026-07-18  
**Suite:** real acceptance projects `mobile-en`, `mobile-fa`, `small-mobile-en`, `small-mobile-fa` (390×844 and 320×700) plus mocked mobile regression  
**KEY_KEPT:** yes

## Mobile results

| Spec | Result |
|------|--------|
| Real acceptance mobile-en/fa | see [STAGE_10_4_ACCEPTANCE_RESULTS.md](STAGE_10_4_ACCEPTANCE_RESULTS.md) |
| Real acceptance small-mobile-en/fa (320×700) | see Stage 10.4 results — overflow + launcher required |
| Stage 9.1 launcher (mocked mobile) | retained regression |
| Stage 10 services (mocked mobile) | retained regression |

## Notes

- Real acceptance requires live API + `AcceptanceSeeder` fixtures; mocked suite does not count as production acceptance.
- No required workflow may be skipped merely because the viewport is mobile.

## Related

- [UI_ACCEPTANCE_RESULTS.md](UI_ACCEPTANCE_RESULTS.md)
- [UI_TEST_MATRIX.md](UI_TEST_MATRIX.md)
