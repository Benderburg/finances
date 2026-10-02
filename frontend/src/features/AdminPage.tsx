import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { get } from "../data/api";
import type { List, User } from "../domain/types";
import { useT } from "../i18n";
import { Editor, Pager, State } from "../components/ui";

interface AdminUser extends User {
  last_seen_at: string | null;
  created_at: string;
}
export function AdminPage() {
  const t = useT();
  const [search, setSearch] = useState(""),
    [page, setPage] = useState(1),
    [editor, setEditor] = useState<AdminUser | null>(null);
  const users = useQuery({
      queryKey: ["admin-users", search, page],
      queryFn: () =>
        get<List<AdminUser>>(
          `/admin/users?q=${encodeURIComponent(search)}&page=${page}`,
        ),
    }),
    stats = useQuery({
      queryKey: ["admin-stats"],
      queryFn: () =>
        get<{
          total: number;
          active_30_days: number;
          premium: number;
          admins: number;
          retention_percent: string;
        }>("/admin/stats"),
    });
  return (
    <>
      <div className="page-heading">
        <h1>{t("admin")}</h1>
      </div>
      <State query={stats}>
        {(s) => (
          <div className="flow-grid">
            <section className="panel">
              <h3>{t("users")}</h3>
              <p className="large-money">{s.total}</p>
            </section>
            <section className="panel">
              <h3>{t("retention")}</h3>
              <p className="large-money">
                {s.active_30_days} · {s.retention_percent}%
              </p>
            </section>
            <section className="panel">
              <h3>{t("premium")}</h3>
              <p className="large-money">{s.premium}</p>
            </section>
          </div>
        )}
      </State>
      <label>
        <span>{t("search")}</span>
        <input
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
        />
      </label>
      <section className="panel">
        <State query={users}>
          {(d) => (
            <>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>{t("fullName")}</th>
                      <th>{t("email")}</th>
                      <th>{t("premium")}</th>
                      <th>{t("lastSeen")}</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {d.items.map((u) => (
                      <tr key={u.id}>
                        <td>{u.full_name}</td>
                        <td>{u.email}</td>
                        <td>
                          {u.billing_plan}
                          {u.is_admin ? " · " + t("role") : ""}
                        </td>
                        <td>{u.last_seen_at ?? "—"}</td>
                        <td>
                          <button onClick={() => setEditor(u)}>
                            {t("edit")}
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pager page={page} pages={d.pagination.pages} onPage={setPage} />
            </>
          )}
        </State>
      </section>
      {editor && (
        <Editor
          title={t("edit")}
          path={"/admin/users/" + editor.id}
          method="PATCH"
          fields={[
            {
              key: "full_name",
              label: t("fullName"),
              required: true,
              initial: editor.full_name,
            },
            {
              key: "billing_plan",
              label: t("premium"),
              type: "select",
              initial: editor.billing_plan,
              required: true,
              options: [
                { value: "regular", label: t("regularPlan") },
                { value: "premium", label: t("premium") },
              ],
            },
            {
              key: "is_admin",
              label: t("role"),
              type: "checkbox",
              initial: editor.is_admin,
            },
          ]}
          onClose={() => setEditor(null)}
        />
      )}
    </>
  );
}
