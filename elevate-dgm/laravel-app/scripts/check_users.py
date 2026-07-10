import sqlite3

p = r"C:/Users/PLPASIG/ElevateHub/elevate-dgm/laravel-app/database/database.sqlite"
conn = sqlite3.connect(p)
cur = conn.cursor()
try:
    cur.execute("SELECT id, email, name, role FROM users LIMIT 10")
    rows = cur.fetchall()
    print('ROWS_COUNT:', len(rows))
    for r in rows:
        print('id:', r[0], 'email:', r[1], 'name:', r[2], 'role:', r[3])
except Exception as e:
    print('ERR', e)
finally:
    conn.close()
