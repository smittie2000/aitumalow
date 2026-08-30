# Changelog

All notable changes to Aitumalow will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Added node and connection context menus with confirmation before deletion.
- Added multi-selection and keyboard deletion that avoids capturing keystrokes
  from form controls.

### Changed

- Upgraded React Flow to 12.11.5 and Dagre to 3.1.1.
- Improved canvas navigation, selection auto-panning, visible-element rendering,
  edge interaction targets, and editor store subscriptions.
- Updated automatic layout to use measured node dimensions, preserve parallel
  connections, handle cyclic graphs, and leave sticky notes in place.
- Kept node configuration and label edits local until the explicit Save action.

### Fixed

- Added usable defaults when creating Loop and Delay control nodes.
- Prevented editor controls from submitting a surrounding Filament or Livewire
  form.

[Unreleased]: https://github.com/smittie2000/aitumalow/compare/v0.2.0...HEAD
