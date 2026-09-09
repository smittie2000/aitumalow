# Changelog

All notable changes to Aitumalow will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-09-09

### Added

- Searchable action picker with category filters and contextual **+** buttons
  that create and connect the next step from an unconnected output.
- Connection retry that reuses an action already created when saving its edge
  fails, plus an empty-canvas starting point.

### Changed

- Redesigned the editor around a larger canvas, clearer action cards, floating
  action/settings panels, and separate run-history and version views.
- Made top-to-bottom the default layout, with inputs above steps, outputs below,
  and branches spread horizontally. Saved positions remain until Auto Layout
  is requested.
- Upgraded the private runtime requirement from `2.0.0-rc.52` to
  `durable-workflow/workflow:^2.0.9`; validated against stable 2.0.9.
- Updated runtime upgrade guidance and prepared the
  [0.4.0 release notes](docs/releases/0.4.0.md).

### Fixed

- Preserved unsaved step settings when opening and closing the action picker.
- Improved mobile header/panel layout and kept the first-step button clickable.
- Adopted upstream fixes for durable cleanup replay, early delayed-job delivery,
  metadata projection, child-completion recovery, and activity-timeout replay,
  alongside the earlier stable runtime fixes.

## [0.3.0] - 2026-08-30

### Added

- Added editor-visible immutable version history with live-version markers and
  a visual comparison against the current draft before restoring any published
  version.
- Added version-list, compare-draft, and restore-draft endpoints for
  host-mounted editor APIs.
- Added canvas-level workflow validation feedback: invalid nodes and
  connections are highlighted, and actionable errors locate the affected
  graph element.
- Added MCP whole-draft authoring with `get_workflow_draft` and
  `save_workflow_draft`, including transactional graph validation, stale-draft
  detection, and protection of the active immutable revision.
- Added node and connection context menus with confirmation before deletion.
- Added multi-selection and keyboard deletion that avoids capturing keystrokes
  from form controls.
- Added nested workflow-folder management in the workflow library, including
  root and child folder creation, rename, move, and confirmed empty-folder
  deletion.
- Added path-aware folder navigation, an explicit Unfiled view, and mobile
  access to the folder tree and management actions.
- Added a compact, path-aware folder picker to the editor header.

### Changed

- Separated publishing from deactivation in the editor so an active workflow
  can publish draft changes without interrupting its current live version.
- Included editor node positions in newly published revision snapshots so a
  restored draft keeps its visual layout.
- Upgraded React Flow to 12.11.5 and Dagre to 3.1.1.
- Improved canvas navigation, selection auto-panning, visible-element rendering,
  edge interaction targets, and editor store subscriptions.
- Updated automatic layout to use measured node dimensions, preserve parallel
  connections, handle cyclic graphs, and leave sticky notes in place.
- Kept node configuration and label edits local until the explicit Save action.
- Kept all folder administration on the workflow-library surface; the embedded
  editor only exposes workflow location so folders do not displace the node
  palette, canvas, run controls, or node configuration.
- Made new workflows inherit the folder currently selected in the workflow
  library.
- Kept the default folders API as an ordered flat collection while making its
  tree representation deterministic and complete at every nesting depth.

### Fixed

- Added usable defaults when creating Loop and Delay control nodes.
- Prevented editor controls from submitting a surrounding Filament or Livewire
  form.
- Prevented deletion of folders that still contain workflows or child folders,
  and prevented cyclic folder parent relationships.
- Restored visibility of deeply nested folders and used full folder paths to
  disambiguate repeated names.
- Improved keyboard, touch, and screen-reader access to folder actions,
  confirmations, and API error feedback.
- Restored clear workflow reactivation feedback by surfacing activation and
  validation errors in both the workflow library and editor, while preventing
  duplicate status-toggle requests.

[Unreleased]: https://github.com/smittie2000/aitumalow/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/smittie2000/aitumalow/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/smittie2000/aitumalow/compare/v0.2.0...v0.3.0
