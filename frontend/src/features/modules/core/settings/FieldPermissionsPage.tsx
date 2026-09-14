'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage, unwrapData } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FieldPermRow = {
  id?: number;
  entity_type: string;
  field_name: string;
  view_roles: string[];
  edit_roles: string[];
  mask_view?: boolean;
  mask_strategy?: string | null;
};

type IndexPayload = {
  defaults?: unknown;
  matrix?: unknown;
  rows?: FieldPermRow[];
};

export function FieldPermissionsPage() {
  const [rows, setRows] = useState<FieldPermRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [entityType, setEntityType] = useState('lead');
  const [fieldName, setFieldName] = useState('');
  const [viewRoles, setViewRoles] = useState('system_manager,team_member');
  const [editRoles, setEditRoles] = useState('system_manager');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/core/field-permissions');
      const payload = unwrapData<IndexPayload>(res) ?? (res.data as IndexPayload);
      setRows(Array.isArray(payload?.rows) ? payload.rows : []);
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function save() {
    if (!fieldName.trim()) return;
    setSaving(true);
    setError(null);
    try {
      await apiClient.put('/v1/core/field-permissions', {
        entity_type: entityType.trim(),
        field_name: fieldName.trim(),
        permissions: {
          view: viewRoles.split(',').map((s) => s.trim()).filter(Boolean),
          edit: editRoles.split(',').map((s) => s.trim()).filter(Boolean),
        },
      });
      setFieldName('');
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }

  async function remove(id?: number) {
    if (!id) return;
    setSaving(true);
    try {
      await apiClient.delete(`/v1/core/field-permissions/${id}`);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-4 p-4">
      <Card>
        <CardHeader>
          <CardTitle>Field permissions</CardTitle>
          <CardDescription>
            Matrix of view/edit roles per CRM/Core field. Empty list means backend defaults apply.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1">
              <Label>Entity</Label>
              <Input value={entityType} onChange={(e) => setEntityType(e.target.value)} placeholder="lead|account|user" />
            </div>
            <div className="space-y-1">
              <Label>Field</Label>
              <Input value={fieldName} onChange={(e) => setFieldName(e.target.value)} placeholder="mobile" />
            </div>
            <div className="space-y-1">
              <Label>View roles (comma)</Label>
              <Input value={viewRoles} onChange={(e) => setViewRoles(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>Edit roles (comma)</Label>
              <Input value={editRoles} onChange={(e) => setEditRoles(e.target.value)} />
            </div>
          </div>
          <div className="flex gap-2">
            <Button type="button" onClick={() => void save()} disabled={saving || !fieldName.trim()}>
              Save rule
            </Button>
            <Button type="button" variant="outline" onClick={() => void load()}>
              Refresh
            </Button>
          </div>
          <ul className="space-y-2 text-sm">
            {rows.map((r) => (
              <li key={r.id ?? `${r.entity_type}.${r.field_name}`} className="flex items-center justify-between gap-2 rounded border px-3 py-2">
                <span className="font-mono text-xs">
                  {r.entity_type}.{r.field_name}
                  <span className="text-muted-foreground"> view=[{(r.view_roles ?? []).join(',')}] edit=[{(r.edit_roles ?? []).join(',')}]</span>
                </span>
                {r.id ? (
                  <Button type="button" size="sm" variant="ghost" onClick={() => void remove(r.id)} disabled={saving}>
                    Delete
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
          {!loading && rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">No custom rules — defaults from backend matrix apply.</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
  );
}
