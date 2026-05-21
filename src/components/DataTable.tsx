import type { ReactNode } from "react";

export function DataTable<T>({
  columns,
  rows,
  getKey
}: Readonly<{
  columns: Array<{ label: string; render: (row: T) => ReactNode }>;
  rows: T[];
  getKey: (row: T) => string | number;
}>) {
  return (
    <table className="table">
      <thead>
        <tr>
          {columns.map((column) => (
            <th key={column.label}>{column.label}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={getKey(row)}>
            {columns.map((column) => (
              <td key={column.label}>{column.render(row)}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
