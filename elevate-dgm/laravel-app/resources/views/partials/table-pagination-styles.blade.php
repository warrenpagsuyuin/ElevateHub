        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #edf0f4;
            color: #475569;
            font-size: 13px;
            font-weight: 800;
        }

        .pagination-summary {
            line-height: 1.3;
            white-space: nowrap;
        }

        .pagination-page-size {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            white-space: nowrap;
        }

        .pagination-page-size select {
            height: 42px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font: inherit;
            padding: 0 10px;
            outline: none;
        }

        .pagination-links {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .pagination-link,
        .pagination-current,
        .pagination-disabled {
            min-width: 42px;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            color: #0f172a;
            font-size: 13px;
            font-weight: 900;
            line-height: 1;
            padding: 0 13px;
            text-decoration: none;
        }

        .pagination-link:hover {
            border-color: #e33c24;
            background: #fff7f5;
            color: #e33c24;
        }

        .pagination-link:focus-visible,
        .pagination-page-size select:focus-visible { outline: 3px solid rgba(227, 60, 36, 0.28); outline-offset: 2px; }
        .pagination-link:active { transform: translateY(1px); }

        .pagination-current {
            background: #e33c24;
            border-color: #e33c24;
            color: #fff;
        }

        .pagination-disabled {
            background: #f8fafc;
            color: #94a3b8;
            opacity: 1;
            cursor: not-allowed;
        }

        .pagination-ellipsis { min-width: 20px; text-align: center; color: #667085; }
        .pagination-mobile-status { display: none; color: #475569; font-weight: 900; }
        .pagination-loading { position: relative; }
        .pagination-loading::after { width: 16px; height: 16px; content: ""; border: 2px solid #f6c5bb; border-top-color: #e33c24; border-radius: 50%; animation: pagination-spin 0.7s linear infinite; }
        .pagination-loading .pagination-links { opacity: 0.55; }
        .pagination-loading-status { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
        @keyframes pagination-spin { to { transform: rotate(360deg); } }

        .table-card .pagination-bar {
            margin: 0;
            padding: 17px 34px 24px;
        }

        .directory-card .pagination-bar {
            margin: 0;
            padding: 17px 20px 20px;
            border-top: 1px solid #edf0f4;
        }

        .directory-card .pagination-link,
        .directory-card .pagination-current,
        .directory-card .pagination-disabled {
            border-color: #d9dee7;
            color: #0f172a;
        }

        .directory-card .pagination-link:hover {
            border-color: #e33c24;
            background: #fff7f5;
            color: #e33c24;
        }

        .directory-card .pagination-page-size select {
            border-color: #d9dee7;
            background: #fff;
            color: #0f172a;
        }

        .directory-card .pagination-current {
            background: #e33c24;
            border-color: #e33c24;
            color: #fff;
        }

        .directory-card .pagination-disabled {
            background: #f8fafc;
            color: #94a3b8;
            opacity: 1;
        }

        @media (max-width: 640px) {
            .pagination-bar {
                align-items: flex-start;
                flex-direction: column;
            }

            .table-card .pagination-bar,
            .directory-card .pagination-bar {
                padding-left: 20px;
                padding-right: 20px;
            }

            .pagination-summary {
                white-space: normal;
            }

            .pagination-page-size {
                width: 100%;
                justify-content: space-between;
            }

            .pagination-links {
                justify-content: flex-start;
            }
        }

        @media (max-width: 480px) {
            .pagination-links { width: 100%; justify-content: space-between; }
            .pagination-links .pagination-link:not(:first-child):not(:last-child),
            .pagination-links .pagination-current,
            .pagination-links .pagination-ellipsis { display: none; }
            .pagination-links .pagination-mobile-status { display: inline-flex; order: 2; }
            .pagination-links .pagination-disabled:first-child,
            .pagination-links .pagination-link:first-child { order: 1; }
            .pagination-links .pagination-disabled:last-child,
            .pagination-links .pagination-link:last-child { order: 3; }
        }
