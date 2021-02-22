# Upgrade 1.x to 2.0

## Dependencies
- Bumped the required php version to >=8.0
- Bumped the required symfony component versions to ^5.2

## Changes
The main thing which changed is that the marker interface `RequestDto` got removed and is now replaced by the `FromRequest`. Every parameter in a controller with this attribute will be passed on to the `RequestDtoResolver` and handled by it. This removes the need to implement the marker interface and therefore the interface was removed as well.
