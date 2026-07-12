import { render, screen } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { describe, expect, it } from 'vitest';
import { PageEmptyState, PageErrorState, PageLoadingState } from '@/features/shared/ui/PageStates';

const messages = {
  common: {
    empty: 'Nothing here yet.',
    errorGeneric: 'Something went wrong.',
    retry: 'Try again',
    loading: 'Loading…',
  },
};

function wrap(ui: React.ReactNode) {
  return render(
    <NextIntlClientProvider locale="en" messages={messages}>
      {ui}
    </NextIntlClientProvider>,
  );
}

describe('PageStates', () => {
  it('renders empty state default title', () => {
    wrap(<PageEmptyState />);
    expect(screen.getByText('Nothing here yet.')).toBeInTheDocument();
  });

  it('renders error state with retry', () => {
    wrap(<PageErrorState onRetry={() => undefined} />);
    expect(screen.getByText('Something went wrong.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
  });

  it('renders loading state', () => {
    wrap(<PageLoadingState />);
    expect(screen.getByRole('status')).toHaveTextContent('Loading…');
  });
});
