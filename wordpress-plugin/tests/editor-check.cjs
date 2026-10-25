const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const registrations = {};
const wp = {
 element: { createElement: (type, props, ...children) => ({type,props,children}) },
 blockEditor: { InnerBlocks: {Content:function Content(){}}, useBlockProps: x=>x },
 components:{}, data:{}, editPost:{},
 blocks:{registerBlockType:(name,config)=>{registrations[name]=config;}},
 plugins:{registerPlugin:()=>{}}
};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/editor.js','utf8'),{window:{wp},WPGPTVibeEditor:{schema:{},canUnlock:true}});
assert.equal(Object.keys(registrations).length,6);
for(const [name,config] of Object.entries(registrations)){
 assert.equal(config.supports.html,false);
 const saved=config.save();
 if(['wpgptvibe/section','wpgptvibe/card','wpgptvibe/columns'].includes(name)) assert.equal(saved.type,wp.blockEditor.InnerBlocks.Content);
 else assert.equal(saved,null);
}
console.log('PASS: all six editor blocks register and preserve editable child content');
