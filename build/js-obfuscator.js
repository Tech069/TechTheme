#!/usr/bin/env node
/**
 * VexyThemes Advanced JavaScript Obfuscator
 * 
 * Layers:
 * 1. Variable name mangling
 * 2. String encoding (charCode, hex, unicode)
 * 3. Anti-debug / anti-devtools
 * 4. Dead code injection
 * 5. Control flow flattening
 * 6. Proxy wrapping
 */

// Generate random identifier
function randomId(len = 10) {
    const chars = 'abcdefghijklmnopqrstuvwxyz';
    let id = '_';
    for (let i = 0; i < len; i++) id += chars[Math.floor(Math.random() * 26)];
    return id;
}

// Encode string as charCode array
function encodeString(str) {
    const codes = [];
    for (let i = 0; i < str.length; i++) {
        codes.push(str.charCodeAt(i));
    }
    return `String.fromCharCode(${codes.join(',')})`;
}

// Encode string as hex escape
function hexEncode(str) {
    return '\\x' + Array.from(Buffer.from(str, 'utf8')).map(b => b.toString(16).padStart(2, '0')).join('\\x');
}

// Generate anti-debug code
function antiDebug() {
    return `
var _vxd = /./; _vxd.toString = function() {
    var _a = ['log','warn','error','info'];
    var _t = this; 
    _a.forEach(function(m) { 
        _t[m] = function() {}; 
    }); 
    return '';
};
(function() {
    var _dd = false;
    var _de = new Function('debugger');
    setInterval(function() {
        var _st = performance.now();
        _de();
        if (performance.now() - _st > 100) {
            if (!_dd) {
                _dd = true;
                document.body.innerHTML = '';
            }
        }
    }, 2000);
})();
(function(){
    var _c = console;
    Object.defineProperty(window, 'console', {
        get: function() {
            return {
                log: function(){},
                warn: function(){},
                error: function(){},
                info: function(){},
                clear: function(){},
                table: function(){},
                dir: function(){},
                dirxml: function(){},
                group: function(){},
                groupEnd: function(){},
                time: function(){},
                timeEnd: function(){},
                trace: function(){},
                assert: function(){},
                profile: function(){},
                profileEnd: function(){}
            };
        }
    });
})();
`;
}

// Generate dead code
function deadCode() {
    const vars = [];
    for (let i = 0; i < 5; i++) {
        vars.push(`var ${randomId()}=${Math.floor(Math.random()*9999)};`);
    }
    return vars.join('');
}

// Obfuscate API URL construction
function obfuscateUrl(url) {
    const parts = [];
    for (let i = 0; i < url.length; i += 3) {
        const chunk = url.substring(i, i + 3);
        parts.push(encodeString(chunk));
    }
    return parts.join('+');
}

// Wrap function calls in proxy
function wrapInProxy(code) {
    const proxyVar = randomId();
    const fnVar = randomId();
    return `
(function() {
    var ${proxyVar} = new Proxy(function(){}, {
        apply: function(t, th, a) { return t.apply(th, a); },
        get: function(t, p) { return p === 'toString' ? function(){return ''} : t[p]; }
    });
    var ${fnVar} = ${proxyVar};
    ${code}
})();
`;
}

// Main obfuscation function
function obfuscateJsAdvanced(code) {
    let result = code;

    // 1. Encode all string literals
    result = result.replace(/'([A-Za-z][A-Za-z0-9_\-\.\/\:\s]{5,})'/g, (match, str) => {
        if (Math.random() > 0.3) return match; // Partial encoding
        return `'${hexEncode(str)}'`;
    });

    // 2. Encode critical strings (API URLs, endpoints)
    result = result.replace(/\/api\/index/g, () => `' + ${encodeString('/api/')} + ${encodeString('index')} + '`);
    result = result.replace(/\/api\/check/g, () => `' + ${encodeString('/api/check')} + '`);
    result = result.replace(/\/api\/license/g, () => `' + ${encodeString('/api/license')} + '`);
    result = result.replace(/vt-panel-api\.vercel\.app/g, () => hexEncode('vt-panel-api.vercel.app'));

    // 3. Obfuscate function declarations with Proxy
    result = result.replace(/async function (\w+)\(/g, (match, name) => {
        const newName = Math.random() > 0.7 ? randomId() : name;
        return `async function ${newName}(`;
    });

    // 4. Add dead code
    const deadBlock = deadCode();
    result = deadBlock + '\n' + result;

    // 5. Wrap in IIFE with anti-debug
    result = wrapInProxy(antiDebug() + '\n' + result);

    return result;
}

module.exports = { obfuscateJsAdvanced, encodeString, hexEncode, antiDebug, deadCode };
