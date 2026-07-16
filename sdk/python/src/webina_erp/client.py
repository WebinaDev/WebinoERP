"""WebinoERP API client."""

from __future__ import annotations

from typing import Any

import httpx


class ErpClient:
    def __init__(self, base_url: str, token: str | None = None) -> None:
        self.base_url = base_url.rstrip("/")
        self.token = token

    def _headers(self) -> dict[str, str]:
        headers = {"Accept": "application/json"}
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        return headers

    def _request(self, method: str, path: str, **kwargs: Any) -> Any:
        with httpx.Client(base_url=self.base_url, headers=self._headers(), timeout=30.0) as client:
            res = client.request(method, path, **kwargs)
            res.raise_for_status()
            if not res.content:
                return None
            return res.json()

    def login(self, email: str, password: str) -> dict[str, Any]:
        with httpx.Client(base_url=self.base_url, timeout=30.0) as client:
            res = client.post(
                "/api/v1/core/auth/login",
                json={"email": email, "password": password},
            )
            res.raise_for_status()
            return res.json()

    def get_user(self) -> Any:
        return self._request("GET", "/api/v1/core/auth/user")

    def openapi(self) -> Any:
        return self._request("GET", "/api/v1/openapi.json")

    def api(self, method: str, path: str, **kwargs: Any) -> Any:
        return self._request(method, path, **kwargs)
