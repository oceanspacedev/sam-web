<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title')</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            
            :root {
                --bg-primary: #0f0f11;
                --bg-secondary: #18181b;
                --bg-tertiary: #27272a;
                --text-primary: #fafafa;
                --text-secondary: #a1a1aa;
                --text-muted: #71717a;
                --accent: #f59e0b;
                --accent-hover: #d97706;
                --accent-light: #fbbf24;
                --border: #27272a;
                --font-sans: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
                --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, monospace;
            }

            @media (prefers-color-scheme: light) {
                :root {
                    --bg-primary: #ffffff;
                    --bg-secondary: #f4f4f5;
                    --bg-tertiary: #e4e4e7;
                    --text-primary: #18181b;
                    --text-secondary: #52525b;
                    --text-muted: #a1a1aa;
                    --border: #e4e4e7;
                }
            }

            html { 
                font-family: var(--font-sans); 
                line-height: 1.5;
                -webkit-font-smoothing: antialiased;
            }
            
            body {
                background: var(--bg-primary);
                color: var(--text-primary);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .container {
                text-align: center;
                padding: 2rem;
                max-width: 32rem;
            }

            .error-code {
                font-size: 8rem;
                font-weight: 700;
                line-height: 1;
                letter-spacing: -0.025em;
                color: var(--accent);
            }

            .error-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: var(--text-primary);
                margin-top: 1rem;
            }

            .error-message {
                font-size: 1rem;
                color: var(--text-secondary);
                margin-top: 0.75rem;
                line-height: 1.625;
            }

            .back-button {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                margin-top: 2rem;
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: #fff;
                background: var(--accent);
                border: none;
                border-radius: 0.5rem;
                cursor: pointer;
                text-decoration: none;
                transition: background 0.15s ease;
            }

            .back-button:hover {
                background: var(--accent-hover);
            }

            .back-button svg {
                width: 1rem;
                height: 1rem;
            }

            .divider {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-top: 2.5rem;
                color: var(--text-muted);
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }

            .divider::before,
            .divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: var(--border);
            }

            .meta {
                margin-top: 1.5rem;
                font-size: 0.75rem;
                color: var(--text-muted);
                font-family: var(--font-mono);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-code">@yield('code')</div>
            <h1 class="error-title">@yield('message')</h1>
            <p class="error-message">@yield('description', 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.')</p>
            
            <a href="{{ url('/') }}" class="back-button">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Kembali ke Beranda
            </a>

            <div class="divider">{{ config('app.name', 'Laravel') }}</div>
            
            <div class="meta">
                {{ now()->format('Y-m-d H:i:s') }} UTC
            </div>
        </div>
    </body>
</html>
