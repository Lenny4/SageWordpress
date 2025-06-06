import * as React from "react";
import {
  TableInterface,
  TableLineItemInterface,
} from "../../../interface/InputInterface";
import {
  CircularProgress,
  Dialog,
  DialogContent,
  DialogTitle,
  IconButton,
  Tooltip,
} from "@mui/material";
import { getTranslations } from "../../../functions/translations";
import RemoveIcon from "@mui/icons-material/Remove";
import AddIcon from "@mui/icons-material/Add";
import { FormFieldComponent } from "./fields/FormFieldComponent";
import { ResultTableInterface } from "../list/ListSageEntityComponent";

let translations: any = getTranslations();

type State = {
  table: TableInterface;
  transPrefix: string | undefined;
  handleCloseParent?: Function;
};

export const FormTableComponent: React.FC<State> = ({
  table,
  transPrefix,
  handleCloseParent,
}) => {
  const padding = 15;

  const [open, setOpen] = React.useState(false);
  const [searchText, setSearchText] = React.useState("");
  const [searching, setSearching] = React.useState<boolean>(
    typeof table.items === "function",
  );
  const getItems = () => {
    return typeof table.items === "function" ? [] : table.items;
  };
  const [items, setItems] =
    React.useState<TableLineItemInterface[]>(getItems());

  const handleOpen = () => {
    setOpen(true);
  };

  const handleClose = () => {
    setOpen(false);
  };

  const thisOnSelectAdd = (item: TableLineItemInterface) => {
    table.addItem(item.item);
    if (handleCloseParent) {
      handleCloseParent();
    }
  };

  const thisOnSelectRemove = (item: TableLineItemInterface) => {
    table.removeItem(item.item);
  };

  const searchItems = () => {
    if (typeof table.items === "function") {
      const useLocalItems = searchText === "" && table.cacheItemName;
      const cacheItemName = `searchItems-${table.cacheItemName}`;
      let cacheResponse: ResultTableInterface | undefined = undefined;
      if (useLocalItems) {
        try {
          cacheResponse = JSON.parse(localStorage.getItem(cacheItemName));
          table.items(searchText, cacheResponse).then((r) => {
            setItems(r.items);
          });
        } catch (e) {
          // nothing
        }
      }
      setSearching(!cacheResponse);
      table
        .items(searchText)
        .then((r) => {
          if (useLocalItems) {
            localStorage.setItem(cacheItemName, JSON.stringify(r.response));
          }
          setItems(r.items);
        })
        .catch(() => {
          setItems([]);
        })
        .finally(() => {
          setSearching(false);
        });
    }
  };

  React.useEffect(() => {
    const timeoutTyping = setTimeout(
      () => {
        searchItems();
      },
      searchText === "" ? 0 : 500,
    );
    return () => clearTimeout(timeoutTyping);
  }, [searchText]);

  React.useEffect(() => {
    setItems(getItems());
  }, [table.items]);

  return (
    <>
      {table.add && (
        <Dialog onClose={handleClose} open={open} maxWidth="lg">
          <DialogTitle>{translations.sentences.addItem}</DialogTitle>
          <DialogContent>
            <FormTableComponent
              table={table.add.table}
              transPrefix={transPrefix}
              handleCloseParent={handleClose}
            />
          </DialogContent>
        </Dialog>
      )}
      {table.search && (
        <>
          <input
            type={"text"}
            value={searchText}
            onChange={(e) => setSearchText(e.target.value)}
            style={{ width: "100%" }}
            placeholder={translations.words.search}
          />
        </>
      )}
      <table
        style={{
          ...(table.fullWidth && {
            width: "100%",
          }),
        }}
      >
        <thead>
          <tr>
            {table.removeItem && <th></th>}
            {table.addItem && <th></th>}
            {table.headers.map((header, index) => (
              <th
                key={index}
                style={{
                  textAlign: "left",
                  paddingLeft:
                    (index === 0 && !table.removeItem) || header === ""
                      ? 0
                      : padding,
                }}
              >
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {searching ? (
            <>
              <tr>
                <td
                  colSpan={table.headers.length + (table.removeItem ? 1 : 0)}
                  style={{ textAlign: "center" }}
                >
                  <CircularProgress />
                </td>
              </tr>
            </>
          ) : (
            <>
              {items
                .filter((item) => {
                  if (table.search) {
                    return table.search(item.item, searchText);
                  }
                  return true;
                })
                .map((item) => (
                  <tr key={item.identifier}>
                    {table.removeItem && (
                      <td>
                        <Tooltip
                          title={translations.sentences.deleteItem}
                          arrow
                          placement="left"
                        >
                          <IconButton
                            onClick={() => {
                              thisOnSelectRemove(item);
                            }}
                          >
                            <RemoveIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      </td>
                    )}
                    {table.addItem && (
                      <td>
                        <Tooltip
                          title={translations.sentences.addThisItem}
                          arrow
                          placement="left"
                        >
                          <IconButton onClick={() => thisOnSelectAdd(item)}>
                            <AddIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      </td>
                    )}
                    {item.lines.map((cell, indexCell) => {
                      const Dom = cell.Dom;
                      return (
                        <td
                          key={indexCell}
                          style={{ paddingLeft: indexCell === 0 ? 0 : padding }}
                        >
                          {Dom}
                          {cell.field && (
                            <FormFieldComponent
                              key={indexCell}
                              field={cell.field}
                              transPrefix={transPrefix}
                            />
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
            </>
          )}
        </tbody>
      </table>
      {typeof table.items === "function" && !searching && (
        <div style={{ textAlign: "center" }}>
          {translations.sentences.modifySearchToFindMore}
        </div>
      )}
      {table.add && (
        <div style={{ textAlign: "center" }}>
          <Tooltip
            title={translations.sentences.addItem}
            arrow
            placement="bottom"
          >
            <IconButton>
              <AddIcon fontSize="small" onClick={handleOpen} />
            </IconButton>
          </Tooltip>
        </div>
      )}
    </>
  );
};
