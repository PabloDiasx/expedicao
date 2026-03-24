# Nomus ERP API Reference (Postman Documenter)

Source (official):
- https://documenter.getpostman.com/view/22813773/2s93JutNgM
- Raw collection endpoint used: https://documenter.gw.postman.com/api/collections/22813773/2s93JutNgM?segregateAuth=true&versionTag=latest

Local snapshot:
- docs/integrations/nomus/collection_2s93JutNgM.json

Fetched on:
- 2026-03-24

## Core Rules (from collection description)

- Auth: `Authorization: Basic chave-integracao-rest`
- Base URL pattern: `https://empresa.nomus.com.br/empresa/rest`
- Pagination (list endpoints): `?pagina=1` (default is page 1)
- Filtering (list endpoints):
  - `?query=campo1=valor1;campo2=valor2`
  - Date filter style: `?query=campoData>yyyy-mm-ddTHH:mm:ss`
- Throttling:
  - HTTP `429 Too Many Requests`
  - response field `tempoAteLiberar` (seconds)
  - integration must wait this time and retry

## Collection Size

- Top-level groups: 65
- Endpoints found: 151

## Fiscal/NFe Endpoints (important for this system)

- `GET /nfes` - list electronic invoices (NF-e)
- `GET /nfes/:id` - invoice detail
- `GET /nfes/cce/:id` - CCE data
- `GET /nfes/danfe/:id` - DANFE file/content
- `GET /nfses` - list service invoices (NFS-e)
- `GET /nfses/:id` - service invoice detail

## Notes

- Keep this file as the local source of truth for implementation decisions.
- Before changing integration behavior, re-fetch the collection endpoint and compare diffs.
