# Changelog

All notable changes to Aitumalow will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

[Unreleased]: https://github.com/smittie2000/aitumalow/compare/v0.2.0...HEAD
