import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function proxy(request: NextRequest) {
  const allCookies = request.cookies.getAll();
  
  if (request.nextUrl.searchParams.get('clearSession') === 'true') {
    const url = request.nextUrl.clone();
    url.searchParams.delete('clearSession');
    const response = NextResponse.redirect(url);
    const sessionCookies = allCookies.filter(c => c.name.endsWith('_session') || c.name.endsWith('-session'));
    sessionCookies.forEach(c => {
      response.cookies.delete(c.name);
    });
    return response;
  }

  // Check if any cookie ends with _session or -session
  const hasSession = allCookies.some(c => c.name.endsWith('_session') || c.name.endsWith('-session'));
  console.log("HAS SESSION:", hasSession, "PATH:", request.nextUrl.pathname);

  const isAuthRoute = request.nextUrl.pathname.startsWith('/login') || request.nextUrl.pathname.startsWith('/register');

  if (isAuthRoute) {
    if (hasSession) {
      return NextResponse.redirect(new URL('/dashboard', request.url));
    }
    return NextResponse.next();
  }

  if (!hasSession) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/dashboard/:path*', '/chat/:path*', '/login', '/register'],
};

export default proxy;
