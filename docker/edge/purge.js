// Edge cache purge without ngx_cache_purge (stock nginx:alpine ships njs).
// Called by tenant frontends after /webino-revalidate (WebinoDashboard frontend/src/lib/edge-purge.ts).
//   PURGE|GET|POST /purge/<path>   headers: x-purge-secret (must equal env EDGE_PURGE_SECRET), x-purge-host
// "/*" (or a path ending in "*") purges every cached entry of that host / path prefix.
// Matches on the "KEY:" header nginx writes into each cache file, so it covers every zone and
// key variant (webina_html "$host$request_uri", pages "$host$uri$is_args$args|$edge_locale").
import fs from 'fs';

var ZONES = ['/var/cache/nginx/webina_html', '/var/cache/nginx/pages'];

function walk(dir, out) {
    var names;
    try { names = fs.readdirSync(dir); } catch (e) { return; }
    for (var a = 0; a < names.length; a++) { var n = names[a];
        var p = dir + '/' + n;
        var st;
        try { st = fs.statSync(p); } catch (e) { continue; }
        if (st.isDirectory()) walk(p, out); else out.push(p);
    }
}

function keyOf(file) {
    var buf;
    try {
        var fd = fs.openSync(file, 'r');
        buf = Buffer.alloc(4096);
        var n = fs.readSync(fd, buf, 0, 4096, 0);
        fs.closeSync(fd);
        buf = buf.subarray(0, n);
    } catch (e) {
        try { buf = fs.readFileSync(file); } catch (e2) { return null; }
    }
    var s = buf.toString('utf8');
    var i = s.indexOf('\nKEY: ');
    if (i < 0) return null;
    var j = s.indexOf('\n', i + 6);
    return j < 0 ? null : s.substring(i + 6, j);
}

function purge(r) {
    var secret = process.env.EDGE_PURGE_SECRET || '';
    if (!secret || r.headersIn['x-purge-secret'] !== secret) { r.return(403, 'forbidden\n'); return; }
    var host = (r.headersIn['x-purge-host'] || '').toLowerCase();
    if (!/^[a-z0-9.-]+(:\d+)?$/.test(host)) { r.return(400, 'x-purge-host required\n'); return; }
    var path = r.variables.purge_path || '/';
    var wildcard = path.endsWith('*');
    var prefix = host + (wildcard ? path.slice(0, -1) : path);
    var purged = 0;
    for (var b = 0; b < ZONES.length; b++) { var z = ZONES[b];
        var files = [];
        walk(z, files);
        for (var c = 0; c < files.length; c++) { var f = files[c];
            var k = keyOf(f);
            if (k === null) continue;
            var ok = wildcard ? k.startsWith(prefix)
                : (k === prefix || k.startsWith(prefix + '?') || k.startsWith(prefix + '|'));
            if (ok) { try { fs.unlinkSync(f); purged++; } catch (e) {} }
        }
    }
    r.return(purged ? 200 : 404, JSON.stringify({ purged: purged }) + '\n');
}

export default { purge };
