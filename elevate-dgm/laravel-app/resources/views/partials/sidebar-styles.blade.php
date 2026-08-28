        :root {
            --sidebar-width: 292px;
            --sidebar-bg: #000000;
            --sidebar-panel: #111111;
            --sidebar-red: #e33c24;
            --sidebar-red-hover: #ff6b53;
            --sidebar-red-dark: #c92f1b;
            --sidebar-active-bg: #ffffff;
            --sidebar-active-text: #08111f;
        }

        .page {
            align-items: stretch;
        }

        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            height: 100vh;
            min-height: 100vh;
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow-x: hidden;
            overflow-y: auto;
            background: var(--sidebar-bg);
            color: #ffffff;
            padding: 22px 18px;
            scrollbar-color: rgba(255, 255, 255, 0.24) transparent;
            scrollbar-width: thin;
        }

        .sidebar .brand {
            width: 100%;
            height: 132px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            padding: 18px;
            margin: 0 0 18px;
            border-radius: 8px;
            background: var(--sidebar-panel);
        }

        .sidebar .brand-icon {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar .brand-icon img {
            display: block;
            width: 100%;
            max-width: 240px;
            max-height: 76px;
            height: auto;
            object-fit: contain;
        }

        .menu {
            display: flex;
            flex: 1 1 auto;
            min-height: 0;
            flex-direction: column;
            gap: 10px;
            padding: 4px 0;
        }

        .menu a {
            width: 100%;
            min-height: 58px;
            display: flex;
            align-items: center;
            padding: 0 22px;
            border-radius: 8px;
            color: var(--sidebar-red);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.2;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .menu a:hover {
            background: rgba(227, 60, 36, 0.12);
            color: var(--sidebar-red-hover);
        }

        .menu a.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
        }

        .signout {
            width: 100%;
            display: flex;
            justify-content: center;
            flex: 0 0 auto;
            margin-top: auto;
            padding: 24px 0 0;
        }

        .signout form {
            width: 100%;
            display: flex;
            justify-content: center;
            margin: 0;
        }

        .signout button {
            width: min(214px, 100%);
            min-height: 48px;
            border: none;
            border-radius: 999px;
            background: var(--sidebar-red);
            color: #ffffff;
            cursor: pointer;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
            padding: 0 20px;
        }

        .signout button:hover {
            background: var(--sidebar-red-dark);
        }

        @media (max-height: 760px) and (min-width: 901px) {
            .sidebar {
                padding: 18px 16px;
            }

            .sidebar .brand {
                height: 116px;
                margin-bottom: 14px;
            }

            .menu {
                gap: 6px;
            }

            .menu a {
                min-height: 50px;
                font-size: 16px;
            }

            .signout {
                padding-top: 16px;
            }
        }

        @media (max-width: 900px) {
            .page {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                max-width: none;
                height: auto;
                min-height: 0;
                position: relative;
                padding: 16px;
            }

            .sidebar .brand {
                height: 96px;
                padding: 14px;
                margin-bottom: 12px;
            }

            .sidebar .brand-icon img {
                max-width: 220px;
                max-height: 62px;
            }

            .menu {
                flex: 0 0 auto;
                flex-direction: row;
                flex-wrap: nowrap;
                gap: 8px;
                overflow-x: auto;
                padding: 0 0 4px;
            }

            .menu a {
                width: auto;
                min-height: 44px;
                flex: 0 0 auto;
                padding: 0 16px;
                font-size: 14px;
                white-space: nowrap;
            }

            .signout {
                justify-content: flex-start;
                margin-top: 12px;
                padding-top: 0;
            }

            .signout form {
                justify-content: flex-start;
            }

            .signout button {
                width: 160px;
                min-height: 44px;
            }
        }
