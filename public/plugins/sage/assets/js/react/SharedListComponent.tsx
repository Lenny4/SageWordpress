import { createRoot } from "react-dom/client";
import React from "react";
import Grid from "@mui/material/Grid";
import List from "@mui/material/List";
import Card from "@mui/material/Card";
import CardHeader from "@mui/material/CardHeader";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import ListItemIcon from "@mui/material/ListItemIcon";
import Checkbox from "@mui/material/Checkbox";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import DragIndicatorIcon from "@mui/icons-material/DragIndicator";
import {
  closestCenter,
  DndContext,
  PointerSensor,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { getTranslations } from "../functions/translations";
import { IconButton, InputAdornment, TextField, Tooltip } from "@mui/material";
import Box from "@mui/material/Box";
import ClearIcon from "@mui/icons-material/Clear";

let translations: any = getTranslations();

interface FieldInterface {
  id: string;
  default: string[];
  description: string;
  label: string;
  type: string;
  options: { [key: string]: string };
}

interface DataInterface {
  optionName: string;
  field: FieldInterface;
  values: string[];
}

interface State {
  data: DataInterface;
  formDom: Element;
}

function not(a: string[], b: string[]) {
  return a.filter((value) => !b.includes(value));
}

function intersection(a: string[], b: string[]) {
  return a.filter((value) => b.includes(value));
}

function union(a: string[], b: string[]) {
  return [...a, ...not(b, a)];
}

const SortableItem = ({
  id,
  label,
  checked,
  onToggle,
}: {
  id: string;
  label: string;
  checked: boolean;
  onToggle: (id: string) => void;
}) => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id });

  // Conditionally apply the cursor style based on whether the item is being dragged
  const cursorStyle = isDragging ? "move" : "grab";

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <div ref={setNodeRef} style={style}>
      <Tooltip title={id} arrow placement="left">
        <ListItemButton role="listitem" onClick={() => onToggle(id)}>
          <ListItemIcon
            {...attributes}
            {...listeners}
            sx={{
              cursor: cursorStyle,
              minWidth: "auto",
            }}
          >
            <DragIndicatorIcon fontSize="small" />
          </ListItemIcon>
          <ListItemIcon>
            <Checkbox checked={checked} tabIndex={-1} disableRipple />
          </ListItemIcon>
          <ListItemText primary={label} />
        </ListItemButton>
      </Tooltip>
    </div>
  );
};

const SharedListComponent: React.FC<State> = ({ data, formDom }) => {
  // https://mui.com/material-ui/react-transfer-list/
  const [checked, setChecked] = React.useState<string[]>([]);
  const compareOption = (a: string, b: string): number => {
    if (!isNaN(Number(a)) && !isNaN(Number(b))) {
      return Number(a) - Number(b);
    }
    const realA = Object.prototype.hasOwnProperty.call(data.field.options, a)
      ? data.field.options[a]
      : a;
    const realB = Object.prototype.hasOwnProperty.call(data.field.options, b)
      ? data.field.options[b]
      : b;
    return realA.localeCompare(realB, undefined, { sensitivity: "base" });
  };

  const [leftSearch, setLeftSearch] = React.useState("");
  const [rightSearch, setRightSearch] = React.useState("");

  const [left, setLeft] = React.useState<string[]>(() => {
    let result = Object.keys(data.field.options).filter(
      (x) => !data.values.includes(x),
    );
    result.sort(compareOption);
    return result;
  });
  const [right, setRight] = React.useState<string[]>(data.values);

  const leftChecked = intersection(checked, left);
  const rightChecked = intersection(checked, right);

  const handleToggle = (value: string) => {
    const currentIndex = checked.indexOf(value);
    const newChecked = [...checked];

    if (currentIndex === -1) {
      newChecked.push(value);
    } else {
      newChecked.splice(currentIndex, 1);
    }

    setChecked(newChecked);
  };

  const numberOfChecked = (items: string[]) =>
    intersection(checked, items).length;

  const handleToggleAll = (items: string[]) => () => {
    if (numberOfChecked(items) === items.length) {
      setChecked(not(checked, items));
    } else {
      setChecked(union(checked, items));
    }
  };

  const handleCheckedRight = () => {
    setRight(right.concat(leftChecked));
    setLeftAsc(not(left, leftChecked));
    setChecked(not(checked, leftChecked));
  };

  const handleCheckedLeft = () => {
    setLeftAsc(left.concat(rightChecked));
    setRight(not(right, rightChecked));
    setChecked(not(checked, rightChecked));
  };

  const setLeftAsc = (value: string[]) => {
    value.sort(compareOption);
    setLeft(value);
  };

  const sensors = useSensors(useSensor(PointerSensor));

  const handleDragEnd = (event: any) => {
    const { active, over } = event;
    if (active.id !== over?.id) {
      const oldIndex = right.indexOf(active.id);
      const newIndex = right.indexOf(over.id);
      setRight((items) => arrayMove(items, oldIndex, newIndex));
    }
  };

  const filterItems = (items: string[], search: string): string[] => {
    if (!search.trim()) return items;
    const lowered = search.toLowerCase();
    return items.filter(
      (key) =>
        data.field.options[key].toLowerCase().includes(lowered) ||
        key.toLowerCase().includes(lowered),
    );
  };

  const customList = (
    title: React.ReactNode,
    items: string[],
    sortable = false,
    search: string,
    setSearch: React.Dispatch<React.SetStateAction<string>>,
  ) => {
    const filteredItems = filterItems(items, search);

    return (
      <Card>
        <CardHeader
          sx={{ px: 2, py: 1 }}
          avatar={
            <Checkbox
              onClick={handleToggleAll(filteredItems)}
              checked={
                numberOfChecked(filteredItems) === filteredItems.length &&
                filteredItems.length !== 0
              }
              indeterminate={
                numberOfChecked(filteredItems) !== filteredItems.length &&
                numberOfChecked(filteredItems) !== 0
              }
              disabled={filteredItems.length === 0}
            />
          }
          title={title}
          subheader={`${numberOfChecked(filteredItems)}/${filteredItems.length}`}
        />
        <Divider />
        <Box sx={{ padding: 1 }}>
          <TextField
            size="small"
            placeholder="Rechercher..."
            variant="outlined"
            fullWidth
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            slotProps={{
              input: {
                endAdornment: search ? (
                  <InputAdornment position="end">
                    <IconButton
                      aria-label="Effacer"
                      onClick={() => setSearch("")}
                      edge="end"
                      size="small"
                    >
                      <ClearIcon fontSize="small" />
                    </IconButton>
                  </InputAdornment>
                ) : null,
              },
            }}
          />
        </Box>
        <List
          sx={{
            maxWidth: 500,
            height: 230,
            bgcolor: "background.paper",
            overflow: "auto",
          }}
          dense
          component="div"
          role="list"
        >
          {sortable ? (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext
                items={filteredItems}
                strategy={verticalListSortingStrategy}
              >
                {filteredItems.map((id) => (
                  <SortableItem
                    key={id}
                    id={id}
                    label={data.field.options[id]}
                    checked={checked.includes(id)}
                    onToggle={handleToggle}
                  />
                ))}
              </SortableContext>
            </DndContext>
          ) : (
            filteredItems.map((value: string) => {
              const labelId = `transfer-list-all-item-${value}-label`;
              return (
                <Tooltip title={value} arrow placement="left">
                  <ListItemButton
                    key={value}
                    role="listitem"
                    onClick={() => handleToggle(value)}
                  >
                    <ListItemIcon>
                      <Checkbox
                        checked={checked.includes(value)}
                        tabIndex={-1}
                        disableRipple
                      />
                    </ListItemIcon>
                    <ListItemText
                      id={labelId}
                      primary={data.field.options[value]}
                    />
                  </ListItemButton>
                </Tooltip>
              );
            })
          )}
        </List>
      </Card>
    );
  };

  const updateFormDom = () => {
    const allSelectDom = formDom.querySelector('[data-2-select-target="all"]');
    const selectedSelectDom = formDom.querySelector(
      '[data-2-select-target="selected"]',
    );

    $(allSelectDom).html("");
    for (const field of left) {
      $(allSelectDom).append(
        '<option value="' + field + '">' + field + "</option>",
      );
    }

    $(selectedSelectDom).html("");
    for (const field of right) {
      $(selectedSelectDom).append(
        '<option value="' + field + '">' + field + "</option>",
      );
    }
  };

  React.useEffect(() => {
    updateFormDom();
  }, [right]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    $(formDom).hide();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <Grid
      container
      spacing={2}
      sx={{ justifyContent: "flex-start", alignItems: "center" }}
    >
      <Grid>
        {customList(
          translations.words.allOptions,
          left,
          false,
          leftSearch,
          setLeftSearch,
        )}
      </Grid>
      <Grid>
        <Grid container direction="column" sx={{ alignItems: "center" }}>
          <Button
            sx={{ my: 0.5 }}
            variant="outlined"
            size="small"
            onClick={handleCheckedRight}
            disabled={leftChecked.length === 0}
          >
            &gt;
          </Button>
          <Button
            sx={{ my: 0.5 }}
            variant="outlined"
            size="small"
            onClick={handleCheckedLeft}
            disabled={rightChecked.length === 0}
          >
            &lt;
          </Button>
        </Grid>
      </Grid>
      <Grid>
        {customList(
          translations.words.selectedOptions,
          right,
          true,
          rightSearch,
          setRightSearch,
        )}
      </Grid>
    </Grid>
  );
};

const doms = document.querySelectorAll("[data-shared-lists]");
doms.forEach((dom) => {
  const root = createRoot(dom.querySelector("[data-shared-lists-react]"));
  root.render(
    <SharedListComponent
      data={JSON.parse($(dom).attr("data-shared-lists"))}
      formDom={dom.querySelector("[data-shared-lists-content]")}
    />,
  );
});
