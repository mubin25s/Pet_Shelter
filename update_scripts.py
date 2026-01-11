import os

frontend_dir = r"d:\Pet_Shelter\frontend"
for filename in os.listdir(frontend_dir):
    if filename.endswith(".html"):
        path = os.path.join(frontend_dir, filename)
        with open(path, "r", encoding="utf-8") as f:
            content = f.read()
        
        # Replace js/pet.js with cache busted version
        # Handle both " and ' and already busted versions
        new_content = content.replace('js/pet.js', 'js/pet.js?v=supabase_v10')
        
        if new_content != content:
            with open(path, "w", encoding="utf-8") as f:
                f.write(new_content)
            print(f"Updated {filename}")
