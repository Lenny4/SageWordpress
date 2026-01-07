import * as React from "react";
import { useImperativeHandle } from "react";
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
  parentAddTable?: boolean;
};

interface SearchInterface {
  text: string;
  page: number;
  totalCount?: number;
  searching: boolean;
}

export const FormTableComponent = React.forwardRef(
  ({ table, transPrefix, handleCloseParent, parentAddTable }: State, ref) => {
    const padding = 15;

    const [open, setOpen] = React.useState(false);
    const [searchText, setSearchText] = React.useState<SearchInterface>({
      text: "",
      page: 1,
      searching: typeof table.items === "function",
    });
    const childTableRef = React.useRef(null);
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
        const realSearchText = searchText.text.trim();
        const useLocalItems =
          realSearchText === "" &&
          table.localStorageItemName &&
          searchText.page === 1;
        const localStorageItemName = `searchItems-${table.localStorageItemName}`;
        let cacheResponse: ResultTableInterface | undefined = undefined;
        if (useLocalItems) {
          try {
            cacheResponse = JSON.parse(
              localStorage.getItem(localStorageItemName),
            );
            table
              .items(realSearchText, searchText.page, cacheResponse)
              .then((r) => {
                setItems(r.items);
              });
          } catch (e) {
            // nothing
          }
        }
        setSearchText((x) => {
          return {
            ...x,
            searching: !cacheResponse,
            totalCount: cacheResponse?.totalCount ?? 0,
          };
        });
        table
          .items(realSearchText, searchText.page)
          .then((r) => {
            if (useLocalItems) {
              localStorage.setItem(
                localStorageItemName,
                JSON.stringify(r.response),
              );
            }
            setItems((x) => {
              if (searchText.page > 1) {
                return [...x, ...r.items];
              }
              return r.items;
            });
            setSearchText((x) => {
              return {
                ...x,
                searching: false,
                totalCount: r.response.totalCount,
              };
            });
          })
          .catch((e) => {
            console.error(e);
            // todo toastr
            setItems([]);
            setSearchText((x) => {
              return {
                ...x,
                searching: false,
                totalCount: 0,
              };
            });
          });
      }
    };

    const loadNextPage = () => {
      if (
        searchText.searching ||
        searchText.totalCount === undefined ||
        items.length >= searchText.totalCount
      ) {
        return;
      }
      setSearchText((x) => {
        return {
          ...x,
          searching: true,
          page: x.page + 1,
        };
      });
    };

    useImperativeHandle(ref, () => ({
      loadNextPage() {
        loadNextPage();
      },
    }));

    React.useEffect(() => {
      const timeoutTyping = setTimeout(
        () => {
          searchItems();
        },
        searchText.text === "" || searchText.page > 1 ? 0 : 500,
      );
      return () => clearTimeout(timeoutTyping);
    }, [searchText.text, searchText.page]);

    React.useEffect(() => {
      setItems(getItems());
    }, [table.items]);

    return (
      <>
        {table.add && (
          <Dialog
            onClose={handleClose}
            open={open}
            maxWidth="lg"
            style={{ zIndex: 10_000 }}
          >
            <DialogTitle>{translations.sentences.addItem}</DialogTitle>
            <DialogContent
              onScroll={(e) => {
                // @ts-ignore
                const { scrollTop, scrollHeight, clientHeight } = e.target;
                const isBottom = scrollTop + clientHeight >= scrollHeight - 50; // allow small offset
                if (isBottom) {
                  childTableRef.current?.loadNextPage();
                }
              }}
            >
              <FormTableComponent
                table={table.add.table}
                transPrefix={transPrefix}
                handleCloseParent={handleClose}
                parentAddTable={true}
                ref={childTableRef}
              />
            </DialogContent>
          </Dialog>
        )}
        {table.search && (
          <>
            <input
              type={"text"}
              value={searchText.text}
              onChange={(e) =>
                setSearchText((x) => {
                  return {
                    ...x,
                    text: e.target.value,
                    page: 1,
                  };
                })
              }
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
            {items
              ?.filter((item) => {
                if (table.search) {
                  return table.search(item.item, searchText.text);
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
                        style={{
                          paddingLeft: indexCell === 0 ? 0 : padding,
                        }}
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
            {searchText.searching && (
              <tr>
                <td
                  colSpan={
                    table.headers.length +
                    (table.removeItem ? 1 : 0) +
                    (parentAddTable ? 1 : 0)
                  }
                  style={{ textAlign: "center" }}
                >
                  <CircularProgress size="3rem" />
                </td>
              </tr>
            )}
          </tbody>
        </table>
        {typeof table.items === "function" &&
          !searchText.searching &&
          items.length >= searchText.totalCount && (
            <>
              {searchText.text !== "" ? (
                <div style={{ textAlign: "center" }}>
                  {translations.sentences.modifySearchToFindMore}
                </div>
              ) : (
                <div style={{ textAlign: "center" }}>
                  {translations.sentences.noMoreResults}
                </div>
              )}
            </>
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
  },
);
