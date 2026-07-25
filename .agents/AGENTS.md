# Reglas del Proyecto (Workspace Rules)

- En Laravel, cuando se utilice el helper `auth()` y se encadene un método como `check()` o `id()`, el IDE (PHP Intelephense) puede arrojar un error de "Undefined method" ya que el helper `auth()` retorna un Factory o Guard y sus métodos dinámicos no son detectados en el análisis estático. **Para evitar este error, se debe usar el facade `use Illuminate\Support\Facades\Auth;` importarlo al inicio del archivo y usarlo como `Auth::check()` o `Auth::id()`**.
