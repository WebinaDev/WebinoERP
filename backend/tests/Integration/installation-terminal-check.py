#!/usr/bin/env python3
import os
import pty
import select
import sys
import time

prompts = [
    (b'Administrator name', b'user_002\n'),
    (b'Administrator email', b'test2@example.local\n'),
    (b'Administrator password (at least 12 characters)', b'fake-test-password-002\n'),
    (b'Confirm administrator password', b'fake-test-password-002\n'),
]

pid, fd = pty.fork()
if pid == 0:
    os.execvp(sys.argv[1], sys.argv[1:])

pending = b''
deadline = time.monotonic() + 120
index = 0
while index < len(prompts) and time.monotonic() < deadline:
    readable, _, _ = select.select([fd], [], [], 1)
    if not readable:
        continue
    try:
        chunk = os.read(fd, 4096)
    except OSError:
        break
    if not chunk:
        break
    pending += chunk
    prompt, answer = prompts[index]
    if prompt in pending:
        os.write(fd, answer)
        pending = b''
        index += 1

if index != len(prompts):
    os.kill(pid, 9)
    os.waitpid(pid, 0)
    sys.exit('Installation terminal did not present the expected prompts.')

while time.monotonic() < deadline:
    finished, status = os.waitpid(pid, os.WNOHANG)
    if finished:
        if os.waitstatus_to_exitcode(status) != 0:
            sys.exit('Installation terminal command failed.')
        print('Installation terminal checks passed.')
        sys.exit(0)
    readable, _, _ = select.select([fd], [], [], 1)
    if readable:
        try:
            os.read(fd, 4096)
        except OSError:
            pass

os.kill(pid, 9)
os.waitpid(pid, 0)
sys.exit('Installation terminal timed out.')
