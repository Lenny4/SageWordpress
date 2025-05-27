import * as React from "react";
import {
  TableInterface,
  TableLineItemInterface,
} from "../../../interface/InputInterface";
import { FormFieldComponent } from "./FormFieldComponent";
import {
  Dialog,
  DialogContent,
  DialogTitle,
  IconButton,
  Tooltip,
} from "@mui/material";
import { getTranslations } from "../../../functions/translations";
import RemoveIcon from "@mui/icons-material/Remove";
import AddIcon from "@mui/icons-material/Add";

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

  return (
    <>
      {table.add && (
        <Dialog
          onClose={handleClose}
          open={open}
          fullWidth={true}
          maxWidth="lg"
        >
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
            {table.canDelete && <th></th>}
            {table.addItem && <th></th>}
            {table.headers.map((header, index) => (
              <th
                key={index}
                style={{
                  textAlign: "left",
                  paddingLeft:
                    (index === 0 && !table.canDelete) || header === ""
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
          {table.items
            .filter((item) => {
              if (table.search) {
                return table.search(item.item, searchText);
              }
              return true;
            })
            .map((item, indexItem) => (
              <tr key={indexItem}>
                {table.canDelete && (
                  <td>
                    <Tooltip
                      title={translations.sentences.deleteItem}
                      arrow
                      placement="left"
                    >
                      <IconButton
                        onClick={() => {
                          console.log("todo");
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
          {table.add && (
            <tr>
              <td
                colSpan={table.headers.length + (table.canDelete ? 1 : 0)}
                style={{ textAlign: "center" }}
              >
                <Tooltip
                  title={translations.sentences.addItem}
                  arrow
                  placement="bottom"
                >
                  <IconButton>
                    <AddIcon fontSize="small" onClick={handleOpen} />
                  </IconButton>
                </Tooltip>
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </>
  );
};
