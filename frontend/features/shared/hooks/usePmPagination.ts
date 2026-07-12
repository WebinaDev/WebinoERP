'use client';

import { useCallback, useState } from 'react';

export function usePmPagination(initialPage = 1) {
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [totalPages, setTotalPages] = useState(1);

  const resetPage = useCallback(() => setCurrentPage(1), []);

  return {
    currentPage,
    setCurrentPage,
    totalPages,
    setTotalPages,
    resetPage,
  };
}
