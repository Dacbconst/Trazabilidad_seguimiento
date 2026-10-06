const fs = require('fs');
const readline = require('readline');

const rl = readline.createInterface({
    input: fs.createReadStream('C:/Users/DiegoAntonioConstant/.gemini/antigravity-ide/brain/9456bf68-a8fa-4ca5-9fd4-7a1e5cae0b1b/.system_generated/logs/transcript_full.jsonl')
});

rl.on('line', (line) => {
    if (line.includes('"step_index": 178') || line.includes('"step_index":178')) {
        try {
            const obj = JSON.parse(line);
            const code = obj.tool_calls[0].args.CodeContent;
            const s5 = code.indexOf('id="slide-5"');
            const s6 = code.indexOf('id="slide-6"');
            const end = code.indexOf('</script>');
            console.log("=== SLIDE 5 ===");
            console.log(code.substring(s5, s6));
            console.log("=== SLIDE 6 ===");
            console.log(code.substring(s6, end));
        } catch(e) {
            console.error(e);
        }
    }
});
