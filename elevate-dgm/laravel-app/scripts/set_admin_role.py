import sqlite3
p = r"C:/Users/PLPASIG/ElevateHub/elevate-dgm/laravel-app/database/database.sqlite"
conn = sqlite3.connect(p)
cur = conn.cursor()
try:
    cur.execute("UPDATE users SET role = 'admin' WHERE email = ?", ('admin@elevate.local',))
    conn.commit()
    print('UPDATED', cur.rowcount)
except Exception as e:
    print('ERR', e)
finally:
    conn.close()
