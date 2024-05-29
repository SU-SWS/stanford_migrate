# Stanford Migrate

8.5.10
--------------------------------------------------------------------------------
_Release Date: 2024-004-05_

- Fixed logging in orphan action

8.5.9
--------------------------------------------------------------------------------
_Release Date: 2024-004-05_

- Clear migration plugin cached data before running the importers via cron or UI

8.5.8
--------------------------------------------------------------------------------
_Release Date: 2024-03-14_

- Removed library dependency on seven admin theme.

8.5.7
--------------------------------------------------------------------------------
_Release Date: 2024-02-22_

- Small adjustment to CSV importer form to help with cached config paths.

8.5.6
--------------------------------------------------------------------------------
_Release Date: 2024-02-15_

- Check for 200 response and 0 content size for file download process plugin


8.5.5
--------------------------------------------------------------------------------
_Release Date: 2024-01-17_

-

8.5.4
--------------------------------------------------------------------------------
_Release Date: 2023-09-28_

- Fixed Localist json data fetching when there is no pager.

8.5.3
--------------------------------------------------------------------------------
_Release Date: 2023-09-11_

- Only add readonly styling if modified on the form

8.5.2
--------------------------------------------------------------------------------
_Release Date: 2023-09-08_

- Update unit tests for D10

8.5.1
--------------------------------------------------------------------------------
_Release Date: 2023-08-23_

- Fetch pages when getting source data for localist json

8.5.0
--------------------------------------------------------------------------------
_Release Date: 2023-08-23_

- Added new `localist_json` data source to provide paged results from the Localist API.

8.4.4
--------------------------------------------------------------------------------
_Release Date: 2023-07-28_

- Update event subscribers to match interface declaration

8.4.3
--------------------------------------------------------------------------------
_Release Date: 2023-07-19_

- D8CORE-6816 Fix permissions for disabled migrations
- Removed dependency on seven library that will be removed in D10

8.4.2
--------------------------------------------------------------------------------
_Release Date: 2023-01-23_

- Fixed warning when $row is false
- Added patch for migrate plus

8.4.1
--------------------------------------------------------------------------------
_Release Date: 2023-01-10_

- Fixed update hook.

8.4.0
--------------------------------------------------------------------------------
_Release Date: 2023-01-09_

- HSD8-1370 Add empty_field module to improve form with empty fields (#67)

8.3.2
--------------------------------------------------------------------------------
_Release Date: 2023-01-09_

- Removed migrate_plus patch that has been fixed
- Allow Drupal 10 installation
- Move automation to github actions (#65)

8.3.1
--------------------------------------------------------------------------------
_Release Date: 2022-09-14_

- Fixed mistake in services yml file.

8.3.0
--------------------------------------------------------------------------------
_Release Date: 2022-09-12_

- D8CORE-6235 Refactored hooks & general functions into a service.


8.2.2
--------------------------------------------------------------------------------
_Release Date: 2022-07-08_

- fixed composer namespace to lowercase


8.x-2.1
--------------------------------------------------------------------------------
_Release Date: 2022-05-10_

- Updated batch executable


8.x-2.0
--------------------------------------------------------------------------------
_Release Date: 2022-05-02_

- Updated contrib modules and patch (#56)
- Lock migrate_plus to 5.2 (#55)


8.x-1.24
--------------------------------------------------------------------------------
_Release Date: 2022-04-05_

- Re-enable cache checking


8.x-1.23
--------------------------------------------------------------------------------
_Release Date: 2022-03-17_

- Override the SQL id_map plugin for our migration lookup (#52)
- CSV convert to UTF-8 correctly
- Switch form field access to readonly field widget (#51)


8.x-1.22
--------------------------------------------------------------------------------
_Release Date: 2022-02-02_

- Error check when the migrate instance doesnt load


8.x-1.21
--------------------------------------------------------------------------------
_Release Date: 2022-01-27_

- Fixed the orphan action to compare string and interger values
- D8CORE-5193 Add "Forget" orphan action (#47)
- Migrate process plugin to adjust date values to 11:59pm (#46)
- Deny field access to imported fields on nodes (#44)


8.x-1.20
--------------------------------------------------------------------------------
_Release Date: 2022-01-04_

- Updated migrate_plus patch.


8.x-1.19
--------------------------------------------------------------------------------
_Release Date: 2021-11-18_

- Removed migrate_tools patch that has been fixed
- D8CORE-4677 Migrate process plugin to get the OAuth token for use CAP (#42)
- If the last name is empty, but a first name is parsed, move the first name to the last name. (#41)


8.x-1.18
--------------------------------------------------------------------------------
_Release Date: 2021-10-20_

- Removed unwanted status message
- D8CORE-4886 improvements to the CSV form (#39)
- Merge branch 'master' into 8.x-1.x

8.x-1.17
--------------------------------------------------------------------------------
_Release Date: 2021-10-11_

- D8CORE-4863 Fixed type in help text
- D8CORE-4861 add config readonly ignore pattern for csv migrations

8.x-1.16
--------------------------------------------------------------------------------
_Release Date: 2021-10-08_

- Added a csv_help section that can be configured via the migrate entity config
- Added process to remove line breaks and non-UTF-8 characters
- Adjusted CSV form labels
- Add "Save and import" button on CSV form
- D8CORE-3749 Migrate process plugins to support publications importing (#36)

8.x-1.14
--------------------------------------------------------------------------------
_Release Date: 2021-05-07_

- Composer dependency fixup (a8c8b9d)

8.x-1.13
--------------------------------------------------------------------------------
_Release Date: 2021-04-22_

- HSD8-1025 CSV Importer upload form.

8.x-1.12
--------------------------------------------------------------------------------
_Release Date: 2021-04-13_

- Fixed migration cleanup when an entity is deleted.

8.x-1.11
--------------------------------------------------------------------------------
_Release Date: 2021-04-09_

- Fixed error (eef339e)
- Dont load migrations during installation (3c8a775)
- Improved function performances (86671d8)
- Fixed typo in logger (a386e3a)

8.x-1.10
--------------------------------------------------------------------------------
_Release Date: 2021-03-05_

- HSD8-1002 Add migrate process plugin to validate a string as a url (#28) (69a7011)

8.x-1.9
--------------------------------------------------------------------------------
_Release Date: 2021-02-10_

- Changed method calls that dont exist in D9

8.x-1.8
--------------------------------------------------------------------------------
_Release Date: 2021-02-10_

- Changed method calls that dont exist in D9 (#25) (2586e4f)
- Updated circleci testing (#24) (dcfcb10)

8.x-1.7
--------------------------------------------------------------------------------
_Release Date: 2020-12-04_

- Change constructor argument to accept the interface instead of the class (0bb3732)
- Use the latest migrate_file (#22) (b52b31b)
- phpunit void return annoation (1fb7697)
- D9 Readiness (#21) (8d40220)

8.x-1.6
--------------------------------------------------------------------------------
_Release Date: 2020-11-06_

- D8CORE-2914 Flag content as needs updating on orphan unpublishing (#18) (2d711a2)

8.x-1.5
--------------------------------------------------------------------------------
_Release Date: 2020-10-19_

- D8CORE-2470: Add process plugin to check image dimensions (#15)
- CS-000 Go to the next item in the id map when continueing (#16)

8.x-1.4
--------------------------------------------------------------------------------
_Release Date: 2020-10-05_

- Fixed orphan actions (#13) (b8a26fa)

8.x-1.3
--------------------------------------------------------------------------------
_Release Date: 2020-09-09_

- Filter out null entity ids (#11) (1843069)
- D8CORE-2499 Updated composer license (#10) (e2eff55)

8.x-1.2
--------------------------------------------------------------------------------
_Release Date: 2020-08-07_

- DEVOPS-000: Dont run migrations during site install. (#8) (683364a)

8.x-1.1
--------------------------------------------------------------------------------
_Release Date: 2020-07-13_

- D8CORE-000: Fix cron job creator (#6) (01c8767)

8.x-1.0
--------------------------------------------------------------------------------
_Release Date: 2020-06-17_

- Initial Release
