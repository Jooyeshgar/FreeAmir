#!/usr/bin/env python3
"""Read-only GitLab REST API helper for issue/MR context gathering."""

from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from typing import Any


def env_required(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        print(f"Missing required environment variable: {name}", file=sys.stderr)
        sys.exit(2)
    return value.rstrip("/") if name == "GITLAB_URL" else value


class GitLabClient:
    def __init__(self) -> None:
        self.base_url = env_required("GITLAB_URL")
        self.token = env_required("GITLAB_TOKEN")

    def request(self, path: str, params: dict[str, Any] | None = None) -> Any:
        url = f"{self.base_url}/api/v4{path}"
        if params:
            clean = {k: v for k, v in params.items() if v is not None and v != ""}
            if clean:
                url = f"{url}?{urllib.parse.urlencode(clean, doseq=True)}"

        request = urllib.request.Request(url, headers={"PRIVATE-TOKEN": self.token})
        try:
            with urllib.request.urlopen(request) as response:
                body = response.read().decode("utf-8")
                return json.loads(body) if body else None
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")
            print(f"GitLab API error {exc.code} for {path}: {detail}", file=sys.stderr)
            sys.exit(1)
        except urllib.error.URLError as exc:
            print(f"GitLab API connection error for {path}: {exc.reason}", file=sys.stderr)
            sys.exit(1)

    def request_all(self, path: str, params: dict[str, Any] | None = None) -> list[Any]:
        page = 1
        rows: list[Any] = []
        while True:
            page_params = dict(params or {})
            page_params.update({"page": page, "per_page": 100})
            url = f"{self.base_url}/api/v4{path}"
            clean = {k: v for k, v in page_params.items() if v is not None and v != ""}
            url = f"{url}?{urllib.parse.urlencode(clean, doseq=True)}"
            request = urllib.request.Request(url, headers={"PRIVATE-TOKEN": self.token})
            try:
                with urllib.request.urlopen(request) as response:
                    body = response.read().decode("utf-8")
                    data = json.loads(body) if body else []
                    if not isinstance(data, list):
                        print(f"Expected list response for {path}", file=sys.stderr)
                        sys.exit(1)
                    rows.extend(data)
                    next_page = response.headers.get("X-Next-Page", "")
            except urllib.error.HTTPError as exc:
                detail = exc.read().decode("utf-8", errors="replace")
                print(f"GitLab API error {exc.code} for {path}: {detail}", file=sys.stderr)
                sys.exit(1)
            except urllib.error.URLError as exc:
                print(f"GitLab API connection error for {path}: {exc.reason}", file=sys.stderr)
                sys.exit(1)

            if not next_page:
                return rows
            page = int(next_page)


def project_arg(args: argparse.Namespace) -> str:
    project_id = args.project_id or os.environ.get("GITLAB_PROJECT_ID")
    if not project_id:
        print("Missing project id. Pass --project-id or set GITLAB_PROJECT_ID.", file=sys.stderr)
        sys.exit(2)
    return project_id


def quote_gitlab_id(value: str) -> str:
    return urllib.parse.quote(value, safe="%")


def compact_issue(issue: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": issue.get("id"),
        "iid": issue.get("iid"),
        "project_id": issue.get("project_id"),
        "title": issue.get("title"),
        "state": issue.get("state"),
        "labels": issue.get("labels"),
        "assignees": issue.get("assignees"),
        "milestone": issue.get("milestone"),
        "web_url": issue.get("web_url"),
        "description": issue.get("description"),
    }


def compact_mr(mr: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": mr.get("id"),
        "iid": mr.get("iid"),
        "project_id": mr.get("project_id"),
        "title": mr.get("title"),
        "state": mr.get("state"),
        "source_branch": mr.get("source_branch"),
        "target_branch": mr.get("target_branch"),
        "labels": mr.get("labels"),
        "assignees": mr.get("assignees"),
        "web_url": mr.get("web_url"),
        "description": mr.get("description"),
    }


def cmd_me(client: GitLabClient, _args: argparse.Namespace) -> Any:
    user = client.request("/user")
    return {
        "id": user.get("id"),
        "username": user.get("username"),
        "name": user.get("name"),
        "web_url": user.get("web_url"),
    }


def cmd_issues_assigned(client: GitLabClient, args: argparse.Namespace) -> Any:
    user = client.request("/user")
    username = args.username or user.get("username")
    label = args.label if args.label is not None else os.environ.get("GITLAB_DOING_LABEL", "Doing")
    params = {
        "assignee_username": username,
        "state": args.state,
        "labels": label,
        "scope": "all",
        "order_by": "updated_at",
        "sort": "desc",
    }

    project_id = args.project_id or os.environ.get("GITLAB_PROJECT_ID")
    group_id = args.group_id or os.environ.get("GITLAB_GROUP_ID")
    if project_id:
        path = f"/projects/{quote_gitlab_id(project_id)}/issues"
    elif group_id:
        path = f"/groups/{quote_gitlab_id(group_id)}/issues"
    else:
        path = "/issues"

    return [compact_issue(issue) for issue in client.request_all(path, params)]


def cmd_board_lists(client: GitLabClient, args: argparse.Namespace) -> Any:
    project_id = args.project_id or os.environ.get("GITLAB_PROJECT_ID")
    group_id = args.group_id or os.environ.get("GITLAB_GROUP_ID")
    board_id = args.board_id or os.environ.get("GITLAB_BOARD_ID")

    if project_id:
        owner_path = f"/projects/{quote_gitlab_id(project_id)}"
    elif group_id:
        owner_path = f"/groups/{quote_gitlab_id(group_id)}"
    else:
        print("Missing project/group id. Pass --project-id/--group-id or set GITLAB_PROJECT_ID/GITLAB_GROUP_ID.", file=sys.stderr)
        sys.exit(2)

    if not board_id:
        boards = client.request_all(f"{owner_path}/boards")
        return [
            {
                "id": board.get("id"),
                "name": board.get("name"),
                "web_url": board.get("web_url"),
            }
            for board in boards
        ]

    lists = client.request_all(f"{owner_path}/boards/{board_id}/lists")
    return [
        {
            "id": board_list.get("id"),
            "label": board_list.get("label"),
            "position": board_list.get("position"),
        }
        for board_list in lists
    ]


def cmd_issue_context(client: GitLabClient, args: argparse.Namespace) -> Any:
    project_id = quote_gitlab_id(project_arg(args))
    issue = client.request(f"/projects/{project_id}/issues/{args.issue_iid}")
    related_mrs = client.request_all(f"/projects/{project_id}/issues/{args.issue_iid}/related_merge_requests")

    fallback_mrs: list[dict[str, Any]] = []
    if not related_mrs:
        search_terms = [f"#{args.issue_iid}", issue.get("title", "")]
        seen: set[int] = set()
        for term in search_terms:
            if not term:
                continue
            for mr in client.request_all(f"/projects/{project_id}/merge_requests", {"state": "all", "search": term}):
                mr_id = mr.get("id")
                if isinstance(mr_id, int) and mr_id not in seen:
                    fallback_mrs.append(mr)
                    seen.add(mr_id)

    return {
        "issue": compact_issue(issue),
        "related_merge_requests": [compact_mr(mr) for mr in related_mrs],
        "fallback_merge_requests": [compact_mr(mr) for mr in fallback_mrs],
    }


def cmd_mr_context(client: GitLabClient, args: argparse.Namespace) -> Any:
    project_id = quote_gitlab_id(project_arg(args))
    mr = client.request(f"/projects/{project_id}/merge_requests/{args.mr_iid}")
    changes = client.request(f"/projects/{project_id}/merge_requests/{args.mr_iid}/changes")
    changed_files = []
    for change in changes.get("changes", []):
        changed_files.append(
            {
                "old_path": change.get("old_path"),
                "new_path": change.get("new_path"),
                "new_file": change.get("new_file"),
                "renamed_file": change.get("renamed_file"),
                "deleted_file": change.get("deleted_file"),
            }
        )
    return {"merge_request": compact_mr(mr), "changed_files": changed_files}


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Read-only GitLab issue/MR helper.")
    subparsers = parser.add_subparsers(dest="command", required=True)

    me = subparsers.add_parser("me", help="Fetch current GitLab user.")
    me.set_defaults(func=cmd_me)

    issues = subparsers.add_parser("issues-assigned", help="List issues assigned to a user.")
    issues.add_argument("--username", help="Assignee username. Defaults to current user.")
    issues.add_argument("--project-id", help="Project ID or path. Defaults to GITLAB_PROJECT_ID.")
    issues.add_argument("--group-id", help="Group ID or path. Defaults to GITLAB_GROUP_ID.")
    issues.add_argument("--label", help="Issue label/list name. Defaults to GITLAB_DOING_LABEL or Doing.")
    issues.add_argument("--state", default="opened", help="Issue state. Defaults to opened.")
    issues.set_defaults(func=cmd_issues_assigned)

    board_lists = subparsers.add_parser("board-lists", help="List boards or lists for a project/group.")
    board_lists.add_argument("--project-id", help="Project ID or path. Defaults to GITLAB_PROJECT_ID.")
    board_lists.add_argument("--group-id", help="Group ID or path. Defaults to GITLAB_GROUP_ID.")
    board_lists.add_argument("--board-id", help="Board ID. Defaults to GITLAB_BOARD_ID. Omit to list boards.")
    board_lists.set_defaults(func=cmd_board_lists)

    issue_context = subparsers.add_parser("issue-context", help="Fetch issue and related MR context.")
    issue_context.add_argument("--project-id", help="Project ID or path. Defaults to GITLAB_PROJECT_ID.")
    issue_context.add_argument("--issue-iid", required=True, help="Project-local issue IID.")
    issue_context.set_defaults(func=cmd_issue_context)

    mr_context = subparsers.add_parser("mr-context", help="Fetch MR context and changed-file metadata.")
    mr_context.add_argument("--project-id", help="Project ID or path. Defaults to GITLAB_PROJECT_ID.")
    mr_context.add_argument("--mr-iid", required=True, help="Project-local merge request IID.")
    mr_context.set_defaults(func=cmd_mr_context)

    return parser


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()
    client = GitLabClient()
    result = args.func(client, args)
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
