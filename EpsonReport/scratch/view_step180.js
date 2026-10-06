const fs = require('fs');
const readline = require('readline');

const rl = readline.createInterface({
    input: fs.createReadStream('C:/Users/DiegoAntonioConstant/.gemini/antigravity-ide/brain/9456bf68-a8fa-4ca5-9fd4-7a1e5cae0b1b/.system_generated/logs/transcript.jsonl')
});

rl.on('line', (line) => {
    if (line.includes('"step_index":180') || line.includes('"step_index": 180')) {
        try {
            const obj = JSON.parse(line);
            const code = obj.tool_calls[0].args.CodeContent;
            console.log("=== STEP 180 CODE LENGTH: ", code.length);
            const s3 = code.indexOf('id="slide-3"');
            if (s3 !== -1) {
                console.log(code.substring(s3 - 50, s3 + 1200));
            }
        } catch(e) {
            console.error(e);
        }
    }
});
