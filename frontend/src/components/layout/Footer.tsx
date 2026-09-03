'use client';

export function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="w-full border-t bg-background py-4 text-center text-sm">
      <div className="container flex flex-col items-center justify-between gap-4 md:flex-row">
        <p className="text-muted-foreground">
          © {currentYear} Webina. All rights reserved.
        </p>
        <p className="font-medium text-primary">
          طراحی و توسعه توسط{' '}
          <span className="font-bold">شرکت توسعه کسب و کار وبینا</span>
        </p>
      </div>
    </footer>
  );
}

