#!/usr/bin/env bash
# Legacy name kept for servers and env files that still point here.
# The real script is build-webina-studio-images.sh («وبینا استودیو» / Webina Studio).
exec bash "$(dirname "${BASH_SOURCE[0]}")/build-webina-studio-images.sh" "$@"
