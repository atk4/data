luasql = require "luasql.mysql"

socket = require 'socket'

env = assert(luasql.mysql())
con = assert(env:connect("atk4_test", "root", "atk4_pass_root", "mysql"))
assert(con:execute("ALTER USER 'atk4_test_user'@'%' WITH MAX_USER_CONNECTIONS 2"))
con:close()
env:close()

for i = 1, (1000 * 1000)
do
    if ((i % (5 * 1000)) == 0)
    then
        print("i: " .. (i // 1000) .. "k")
    end

    env = assert(luasql.mysql())
    con = assert(env:connect("atk4_test", "atk4_test_user", "atk4_pass", "mysql"))
    cur = assert(con:execute("SELECT 'test' as v"))

    row = assert(cur:fetch({}))
    assert(row[1] == "test")
    assert(not cur:fetch({}))
    
    cur:close()
    con:close()
    env:close()

    if ((i % 100) == 0)
    then
        socket.sleep(0.1)
    end
end

print("done!")
