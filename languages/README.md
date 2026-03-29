# Super Mechanic Languages

This plugin uses WordPress standard internationalization with textdomain `super-mechanic`.

## Baseline

- Default operational locale: `en_US`
- Supported operational locales:
  - `en_US`
  - `es_ES`
  - `it_IT`

## Notes

- Locale selection is resolved from plugin settings (`sm_settings.business.locale`) with safe fallback to `en_US`.
- WordPress global site locale is not force-overridden.
- Translation files (`.po/.mo`) for additional locales can be added here following WordPress conventions:
  - `super-mechanic-es_ES.po`
  - `super-mechanic-es_ES.mo`
  - `super-mechanic-it_IT.po`
  - `super-mechanic-it_IT.mo`
