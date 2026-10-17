import React from 'react';
import { Link } from '@inertiajs/react';

/** Renders the `links` array of a Laravel paginator (the page keeps the current filters via withQueryString). */
export default function Pagination({ links = [] }) {
  if (links.length <= 3) return null; // previous + 1 page + next = nothing to paginate

  return (
    <nav className="pagination" aria-label="Pagination">
      {links.map((link, i) => {
        const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', 'Prev');

        if (!link.url) return <span key={i} className="disabled">{label}</span>;

        return (
          <Link key={i} href={link.url} className={link.active ? 'active' : ''} preserveScroll>
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
