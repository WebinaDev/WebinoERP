# webina-erp-sdk

```bash
cd WebinoERP/sdk/python
pip install -e .
```

```python
from webina_erp import ErpClient

client = ErpClient("https://erp.example.com", token="...")
print(client.get_user())
```

Regenerate OpenAPI first: `cd WebinoERP/backend && composer openapi`.
