---
name: gitlab-issue-mr-reader
description: Read-only GitLab issue and merge request workflow using GitLab REST API credentials from GITLAB_URL and GITLAB_TOKEN. Use when Codex needs to inspect assigned GitLab issues, board/list items such as Doing, linked merge requests, issue/MR descriptions, labels, notes, references, or diffs before planning or implementing local code changes.
---

# GitLab Issue/MR Reader

## Overview

Use this skill to gather read-only GitLab context before planning or editing local code. Treat GitLab as a source of issue/MR requirements, not as a place to mutate state.

Never create, update, comment on, approve, close, merge, label, assign, or otherwise mutate GitLab resources while using this skill unless the user explicitly changes the scope away from read-only.

## Environment

Require:

- `GITLAB_URL`: Base GitLab URL, such as `https://gitlab.com` or `https://gitlab.example.com`.
- `GITLAB_TOKEN`: Personal/project/group token with read-only API access where possible.

Do not print token values. If credentials are missing, stop and report the missing variable names only.
Read these variables from .env file or environment, but do not hardcode them in the script or code changes.

## Quick Start

Prefer the bundled script for repeatable read-only calls:

```bash
python .agents/skills/gitlab-issue-mr-reader/scripts/gitlab_read.py me
python .agents/skills/gitlab-issue-mr-reader/scripts/gitlab_read.py board-lists --project-id group/project
python .agents/skills/gitlab-issue-mr-reader/scripts/gitlab_read.py issues-assigned --label Doing --state opened
python .agents/skills/gitlab-issue-mr-reader/scripts/gitlab_read.py issue-context --project-id group%2Fproject --issue-iid 123
```

The script prints JSON. Read only the fields needed for the user request, then inspect the local repository normally before planning or implementing code.

## Workflow

1. Confirm `GITLAB_URL` and `GITLAB_TOKEN` are available.
2. Fetch the current user with `me` when the request depends on "my user".
3. Find candidate issues:
   - Prefer `issues-assigned` with `--project-id` when a project is known.
   - Use `--group-id` for group-wide discovery.
   - Use `board-lists` when the Doing tab maps to a board list and the exact label is unknown.
   - Use `--label "${GITLAB_DOING_LABEL:-Doing}"` for Doing-tab style filtering.
4. For a selected issue, fetch `issue-context` to collect:
   - Issue title, state, labels, assignees, milestone, web URL, and description.
   - Related merge requests from GitLab's issue related-MR endpoint.
   - Fallback merge request search results when related MRs are absent.
5. For a selected MR, fetch `mr-context` to collect title, state, source/target branch, description, changed-file metadata, and web URL.
6. Summarize the GitLab context briefly, then ground the implementation in local repo inspection before making code changes.

## REST API Guidance

Use GitLab REST API v4 under `${GITLAB_URL}/api/v4`. Send the token as:

```text
PRIVATE-TOKEN: <GITLAB_TOKEN>
```

Use URL encoding for path-style project/group IDs when calling the API directly, for example `group/subgroup/project` becomes `group%2Fsubgroup%2Fproject`. The bundled script accepts either raw paths or already encoded IDs.

Handle pagination by following `X-Next-Page` until it is empty. Keep `per_page` at `100` for list calls unless there is a reason to reduce it.

Common read-only endpoints:

- Current user: `GET /user`
- Assigned issues: `GET /issues?assignee_username=<username>&state=opened&labels=Doing`
- Project issues: `GET /projects/:id/issues`
- Group issues: `GET /groups/:id/issues`
- Single issue: `GET /projects/:id/issues/:issue_iid`
- Related MRs: `GET /projects/:id/issues/:issue_iid/related_merge_requests`
- Project MRs: `GET /projects/:id/merge_requests`
- Single MR: `GET /projects/:id/merge_requests/:merge_request_iid`
- MR changes/metadata: `GET /projects/:id/merge_requests/:merge_request_iid/changes`
- Project boards/lists: `GET /projects/:id/boards` and `GET /projects/:id/boards/:board_id/lists`
- Group boards/lists: `GET /groups/:id/boards` and `GET /groups/:id/boards/:board_id/lists`

Official docs:

- Issues API: https://docs.gitlab.com/api/issues/
- Merge Requests API: https://docs.gitlab.com/api/merge_requests/
- Users API: https://docs.gitlab.com/api/users/
- Boards API: https://docs.gitlab.com/api/boards/

## Interpreting Issue/MR Context

When the user asks to start work from an issue and related MR:

- Treat issue title/description as the primary requirement source.
- Treat MR description and changed files as implementation context, especially when the MR already contains partial work.
- Check labels, milestones, and assignees for priority and scope hints, but do not overfit to them.
- If multiple candidate issues or MRs match, present the choices with titles, IIDs, and web URLs before editing code.
- If no related MR exists, say so and continue from the issue requirements if enough context exists.

Before making local code changes, inspect the relevant files, existing tests, and project conventions. For financial/accounting logic, require tests and preserve accounting invariants.
