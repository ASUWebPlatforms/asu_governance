#!/bin/bash
# _common.sh - Shared configuration, functions, and UI helpers for ASU Governance scripts.
# This file is sourced by role-manager, add-user, and other governance scripts.
# It should NOT be executed directly.

# ========================== Paths ===========================================

# Use PROJECT_ROOT from the environment (set by AsuGovernanceCommands.php) when
# available.  Fall back to git for standalone / local usage.
if [ -z "${PROJECT_ROOT:-}" ]; then
  PROJECT_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"
fi

# ========================== Color Codes ====================================

RED='\033[0;31m'
CYAN='\033[0;36m'
CYAN_BG='\033[46m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
YELLOW_BG='\033[43m'
BLACK='\033[0;30m'
NC='\033[0m' # No Color

# ========================== Configuration ==================================

DEFAULT_ENVIRONMENT="live"

# Define available Acquia Site Factory stacks
# Keys correspond to ACSF stack numbers
declare -A STACKS=(
  [0]="All stacks"
  [1]="Webspark Stack"
  [3]="Drupal Stack"
  [4]="Webspark Stack [Drupal 9]"
)

# Maps stack number → Drush alias prefix.
# Append the environment (e.g., "live", "test", "dev") to get the full alias.
# Example: STACK_ALIASES[1] + "live" → "@asufactory1.01live"
declare -A STACK_ALIASES=(
  [1]="@asufactory1.01"
  [3]="@asufactory3.03"
  [4]="@asufactory4.04"
)

# Maps stack number → alias file path (relative to project root).
declare -A STACK_ALIAS_FILES=(
  [1]="drush/sites/asufactory1.site.yml"
  [3]="drush/sites/asufactory3.site.yml"
  [4]="drush/sites/asufactory4.site.yml"
)

# Maps stack number → acli download instructions (application name and number).
declare -A STACK_ALIAS_DOWNLOAD_HINTS=(
  [1]="Webspark Stack"
  [3]="ASU Drupal Stack"
  [4]="Webspark Stack Drupal 9"
)

# Maps stack number → Acquia Cloud application UUID.
# Used to pass --cloud-app-uuid to acli, which avoids interactive prompts and
# caching issues.  Retrieve UUIDs with: acli api:applications:list
declare -A STACK_APP_UUIDS=(
  [1]="ed6ba37d-8d64-4a45-8d89-78f9460cf550"
  [3]="6c63152a-3c87-4295-8999-8c3f6b03ae30"
  [4]="e64e5765-54f4-4b6e-9b12-8c4071f2067e"
)

# Define available roles to manage
declare -a ROLES=(
  "administrator"
  "site_builder"
)

# ========================== Common Functions =================================

# Handle initial authentication via Acquia secrets and ssh
function authenticate() {
  if [ -z "${ACQUIA_FACTORY_URL:-}" ]; then echo "Please make sure you have set ACQUIA_FACTORY_URL in your project or global config" && exit 1; fi
  if [ -z "${ACQUIA_API_KEY:-}" ] || [ -z "${ACQUIA_API_SECRET:-}" ]; then echo "Please make sure you have set ACQUIA_API_KEY and ACQUIA_API_SECRET in your project or global config" && exit 1; fi
  if [ -z "${ACQUIA_ACSF_USERNAME:-}" ] || [ -z "${ACQUIA_ACSF_KEY:-}" ]; then echo "Please make sure you have set ACQUIA_ACSF_USERNAME and ACQUIA_ACSF_KEY in your project or global config" && exit 1; fi
  if [ -z "${AH_ORGANIZATION_UUID:-}" ]; then echo "Please make sure you have set AH_ORGANIZATION_UUID in your project or global config" && exit 1; fi
  if ! command -v ddev drush >/dev/null; then echo "Please make sure your project contains drush, ddev composer require drush/drush" && exit 1; fi
  ssh-add -l >/dev/null || { echo "Please 'ddev auth ssh' before running this command." && exit 1; }
  acli auth:login -n --key="${ACQUIA_API_KEY}" --secret="${ACQUIA_API_SECRET}"
  acli auth:acsf-login --username="${ACQUIA_ACSF_USERNAME}" --key="${ACQUIA_ACSF_KEY}" --factory-url="${ACQUIA_FACTORY_URL}"
}

# Validate that username contains only safe characters (alphanumeric, dots, hyphens, underscores)
# and does not start with a dash, to prevent shell option injection.
function validate_username() {
  local username=$1
  if [[ ! "$username" =~ ^[a-zA-Z0-9]+$ ]] || [[ "$username" == -* ]]; then
    return 1
  fi
  return 0
}

# Validate that action is either "add" or "remove"
function validate_action() {
  local action=$1
  if [ "$action" != "add" ] && [ "$action" != "remove" ]; then
    return 1
  fi
  return 0
}

# Extract environment from alias (format: alias.env)
# Example: mysite.dev -> dev, mysite.local -> local
function extract_env_from_alias() {
  local alias=$1
  echo "${alias##*.}"
}

# Validate that environment is not "local"
function validate_env_not_local() {
  local alias=$1
  if [ "$alias" = "@self" ]; then
    return 1
  fi
  local env=$(extract_env_from_alias "$alias")
  if [ "$env" = "local" ]; then
    return 1
  fi
  return 0
}

# Build the full Drush alias for a given stack and environment.
# Sets BUILD_STACK_ALIAS_RESULT with the result (avoids subshell, which
# cannot access associative arrays).
# Usage: build_stack_alias <stack_number> <environment>
# Example: build_stack_alias 1 live → BUILD_STACK_ALIAS_RESULT="@asufactory1.01live"
function build_stack_alias() {
  local stack=$1
  local environment=$2
  local prefix="${STACK_ALIASES[$stack]:-}"
  if [ -z "$prefix" ]; then
    BUILD_STACK_ALIAS_RESULT=""
    return 1
  fi
  BUILD_STACK_ALIAS_RESULT="${prefix}${environment}"
}

# ========================== Alias File Management ============================

# Clean up a downloaded alias file (remove stale drush-script entries)
function cleanup_alias_file() {
  local file="$1"
  local tempfile="${file}.tmp"
  awk '
    /^[[:space:]]+paths:$/ {
      getline l2
      getline l3
      if (l2 ~ /^[[:space:]]+drush-script: drush9$/ && l3 ~ /^$/) next
      print $0; print l2; print l3; next
    }
    { print }
  ' "$file" > "$tempfile" && mv "$tempfile" "$file"
}

# Ensure all required alias files exist. If any are missing, prompt the user
# to download them via acli.
# Uses PROJECT_ROOT to resolve paths, since the script may run from a
# different working directory (e.g., src/Drush/Commands/).
function ensure_alias_files() {
  # Skip alias file checks if the repository is not a stack environment.
  local repo_url
  repo_url="$(git -C "$PROJECT_ROOT" remote get-url origin 2>/dev/null || true)"
  if [[ "$repo_url" != *"asufactory"* ]]; then
    return 0
  fi

  # First, collect missing keys into an array (avoid running interactive
  # commands inside a piped while-read loop, which steals stdin).
  local missing_keys=()
  while IFS= read -r key; do
    local alias_file="${PROJECT_ROOT}/${STACK_ALIAS_FILES[$key]}"
    if [ ! -f "$alias_file" ]; then
      missing_keys+=("$key")
    fi
  done < <(get_real_stack_keys)

  # Now iterate outside the pipe so acli can read from the terminal.
  for key in "${missing_keys[@]}"; do
    local alias_file="${STACK_ALIAS_FILES[$key]}"
    local abs_alias_file="${PROJECT_ROOT}/${alias_file}"
    local hint="${STACK_ALIAS_DOWNLOAD_HINTS[$key]}"
    local app_uuid="${STACK_APP_UUIDS[$key]:-}"

    echo -e "\n${RED}Alias file ${YELLOW}$alias_file${RED} not found.${NC}"

    if [ -n "$app_uuid" ]; then
      # Non-interactive: pass the application UUID directly to avoid caching
      # and prompt issues when looping over multiple stacks.
      echo -e "${GREEN}  Downloading alias for ${YELLOW}${hint}${GREEN}...${NC}"
      (cd "$PROJECT_ROOT" && acli remote:aliases:download "$app_uuid" --no-interaction)
    else
      echo -e "${RED}  The alias file for ${YELLOW}${hint}${RED} is missing, and we don't have an application UUID to download it non-interactively.${NC}"
      echo -e "${CYAN}  Please run the following command to download the missing alias file, then re-run this script:${NC}"
      echo -e "${CYAN}    'acli remote:aliases:download'${NC}"
    fi

    cleanup_alias_file "$abs_alias_file"
  done
}

# ========================== SSH Check =======================================

# Check SSH connectivity (when DDEV is available)
function check_ssh_connectivity() {
  if command -v ddev >/dev/null 2>&1; then
    local output
    output=$(ddev drush @asufactory1.01live list 2>&1)
    if echo "$output" | grep -q "Permission denied (publickey)"; then
      echo "❌ SSH key synchronization failed. Please run 'ddev auth ssh' and try again."
      exit 1
    fi
  fi
}

# ========================== Stack Helper Functions ===========================

# Get sorted stack keys (associative arrays are unordered, so sort numerically)
function get_sorted_stack_keys() {
  printf '%s\n' "${!STACKS[@]}" | sort -n
}

# Get sorted stack keys excluding key 0 ("All stacks" meta-entry).
# Use this when iterating to execute commands on actual stacks.
function get_real_stack_keys() {
  printf '%s\n' "${!STACKS[@]}" | sort -n | grep -v '^0$'
}

# Get list of available stacks (sorted by key)
function get_available_stacks() {
  while IFS= read -r key; do
    echo "${STACKS[$key]}"
  done < <(get_sorted_stack_keys)
}

# Get list of available roles
function get_available_roles() {
  printf '%s\n' "${ROLES[@]}"
}

# ========================== Wizard UI Helpers ================================

# Print menu with options
function print_menu() {
  local title=$1
  shift
  local options=("$@")

  echo -e "$title"
  echo ""
  for i in "${!options[@]}"; do
    echo "  $((i+1))) ${options[$i]}"
  done
  echo ""
}

# Get user input from menu
function get_menu_input() {
  local prompt=$1
  local num_options=$2
  local choice=""

  while [[ ! "$choice" =~ ^[0-9]+$ ]] || [ "$choice" -lt 1 ] || [ "$choice" -gt "$num_options" ]; do
    echo -ne "$prompt" >&2
    read -r choice < /dev/tty

    if [[ ! "$choice" =~ ^[0-9]+$ ]] || [ "$choice" -lt 1 ] || [ "$choice" -gt "$num_options" ]; then
      echo -e "${RED}✗ [error]${NC} Invalid selection. Please enter a number between 1 and $num_options." >&2
      echo "" >&2
    fi
  done

  echo "$choice"
}

# Get text input from user
function get_text_input() {
  local prompt=$1
  local input=""

  while [ -z "$input" ]; do
    echo -ne "$prompt" >&2
    read -r input < /dev/tty

    if [ -z "$input" ]; then
      echo -e "${RED}✗ [error]${NC} Input cannot be empty. Please try again." >&2
    fi
  done

  echo "$input"
}

